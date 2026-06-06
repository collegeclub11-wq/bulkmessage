<?php
namespace Services;

use Exception;

class ExcelProcessor {
    private $db;
    private $tenantId;
    
    public function __construct($db, $tenantId) {
        $this->db = $db;
        $this->tenantId = $tenantId;
    }
    
    public function processContacts($filePath, $originalFileName = null, $groupId = null) {
        $nameToCheck = $originalFileName ?: $filePath;
        $extension = strtolower(pathinfo($nameToCheck, PATHINFO_EXTENSION));
        
        if ($extension === 'csv') {
            return $this->processCsv($filePath, $groupId);
        }
        
        // Check if PhpOffice class exists
        if (class_exists('PhpOffice\PhpSpreadsheet\IOFactory')) {
            return $this->processExcelWithLibrary($filePath, $groupId);
        } else {
            throw new Exception("Excel parser library is not installed. Please upload .csv format instead.");
        }
    }
    
    private function processCsv($filePath, $groupId) {
        $file = fopen($filePath, 'r');
        if (!$file) {
            throw new Exception("Unable to open CSV file.");
        }
        
        $headers = fgetcsv($file);
        if (!$headers) {
            fclose($file);
            throw new Exception("Empty CSV file.");
        }
        
        $phoneColumn = $this->findPhoneColumn($headers);
        if ($phoneColumn === false) {
            fclose($file);
            throw new Exception("Phone number column not found. Columns headers detected: " . implode(', ', $headers));
        }
        
        $contacts = [];
        $errors = [];
        $rowIndex = 2;
        
        while (($row = fgetcsv($file)) !== false) {
            if (empty($row) || count($row) < $phoneColumn) {
                continue;
            }
            
            $phoneNumber = $this->cleanPhoneNumber($row[$phoneColumn]);
            if (!$this->validatePhoneNumber($phoneNumber)) {
                $errors[] = "Row $rowIndex: Invalid phone number '{$row[$phoneColumn]}'";
                $rowIndex++;
                continue;
            }
            
            $contactData = [
                'phone_number' => $phoneNumber,
                'name' => null,
                'custom_fields' => []
            ];
            
            foreach ($headers as $colIndex => $header) {
                if (isset($row[$colIndex])) {
                    $headerLower = strtolower($header);
                    if ($headerLower === 'name') {
                        $contactData['name'] = $row[$colIndex];
                    }
                    if ($colIndex != $phoneColumn) {
                        $contactData['custom_fields'][$header] = $row[$colIndex];
                    }
                }
            }
            
            $contacts[] = $contactData;
            $rowIndex++;
        }
        
        fclose($file);
        
        $inserted = $this->bulkInsertContacts($contacts, $groupId);
        return [
            'total' => $rowIndex - 2,
            'valid' => count($contacts),
            'inserted' => $inserted,
            'errors' => $errors
        ];
    }
    
    private function processExcelWithLibrary($filePath, $groupId) {
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filePath);
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();
        
        $headers = array_shift($rows);
        $phoneColumn = $this->findPhoneColumn($headers);
        
        if ($phoneColumn === false) {
            throw new Exception("Phone number column not found");
        }
        
        $contacts = [];
        $errors = [];
        
        foreach ($rows as $rowIndex => $row) {
            $phoneNumber = $this->cleanPhoneNumber($row[$phoneColumn]);
            
            if (!$this->validatePhoneNumber($phoneNumber)) {
                $errors[] = "Row " . ($rowIndex + 2) . ": Invalid phone number";
                continue;
            }
            
            $contactData = [
                'phone_number' => $phoneNumber,
                'name' => null,
                'custom_fields' => []
            ];
            
            foreach ($headers as $colIndex => $header) {
                if (!empty($row[$colIndex])) {
                    $headerLower = strtolower($header);
                    if ($headerLower === 'name') {
                        $contactData['name'] = $row[$colIndex];
                    }
                    if ($colIndex != $phoneColumn) {
                        $contactData['custom_fields'][$header] = $row[$colIndex];
                    }
                }
            }
            
            $contacts[] = $contactData;
        }
        
        $inserted = $this->bulkInsertContacts($contacts, $groupId);
        
        return [
            'total' => count($rows),
            'valid' => count($contacts),
            'inserted' => $inserted,
            'errors' => $errors
        ];
    }
    
    private function findPhoneColumn($headers) {
        $phoneKeywords = ['phone', 'mobile', 'number', 'whatsapp', 'contact', 'tel'];
        
        foreach ($headers as $index => $header) {
            $headerLower = strtolower($header);
            foreach ($phoneKeywords as $keyword) {
                if (strpos($headerLower, $keyword) !== false) {
                    return $index;
                }
            }
        }
        
        return false;
    }
    
    private function cleanPhoneNumber($number) {
        return preg_replace('/[^0-9]/', '', $number);
    }
    
    private function validatePhoneNumber($number) {
        return strlen($number) >= 8 && strlen($number) <= 15;
    }
    
    private function bulkInsertContacts($contacts, $groupId) {
        $inserted = 0;
        $query = "INSERT INTO contacts (tenant_id, group_id, phone_number, name, custom_fields, created_at) 
                  VALUES (?, ?, ?, ?, ?, NOW())
                  ON DUPLICATE KEY UPDATE 
                  group_id = COALESCE(?, group_id),
                  name = COALESCE(?, name),
                  custom_fields = JSON_MERGE_PATCH(COALESCE(custom_fields, '{}'), ?),
                  updated_at = NOW()";
        
        $stmt = $this->db->prepare($query);
        
        foreach ($contacts as $contact) {
            $customFieldsJson = json_encode($contact['custom_fields']);
            $stmt->execute([
                $this->tenantId,
                $groupId,
                $contact['phone_number'],
                $contact['name'],
                $customFieldsJson,
                
                $groupId,
                $contact['name'],
                $customFieldsJson
            ]);
            
            if ($stmt->rowCount() > 0) {
                $inserted++;
            }
        }
        
        return $inserted;
    }
}
?>
