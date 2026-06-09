<?php
/**
 * Advanced Template Safety Engine with Multi-factor Risk Scoring
 * Implements pattern detection, content sanitization, and safety suggestions
 */
class TemplateSafetyEngine {
    private $riskWeights = [
        'direct_prices' => 15,      // ₹ symbols, explicit pricing
        'phone_numbers' => 25,      // 10-digit numbers
        'excessive_emojis' => 10,   // >5 emojis per 100 chars
        'commercial_language' => 20, // buy, sale, discount, offer
        'urgency_phrases' => 15,    // limited, hurry, today only
        'url_shorteners' => 30,     // bit.ly, tinyurl, etc.
        'excessive_caps' => 10,     // >30% capital letters
        'formatting_chars' => 5,    // ━, ─, ═, * * *, etc.
        'phone_numbers_obscured' => 5 // [PHONE] placeholders
    ];
    
    private $safePhrases = [
        'Hi', 'Hello', 'Thanks', 'Please', 'Available', 'Fresh', 'Quality',
        'Contact us', 'Visit us', 'Located at', 'Open', 'Stock available'
    ];
    
    public function analyzeTemplate(string $message): array {
        $score = 0;
        $flags = [];
        $suggestions = [];
        
        // 1. Price detection (direct)
        if (preg_match_all('/[₹₹]\\s*\\d+/', $message, $matches)) {
            $score += $this->riskWeights['direct_prices'] * min(count($matches[0]), 3);
            $flags[] = 'direct_prices';
            $suggestions[] = 'Replace explicit prices with ranges or "call for price"';
        }
        
        // 2. Phone number detection
        if (preg_match_all('/\\b[6-9]\\d{9}\\b/', $message, $matches)) {
            $score += $this->riskWeights['phone_numbers'] * min(count($matches[0]), 2);
            $flags[] = 'phone_numbers';
            $suggestions[] = 'Remove phone numbers or use placeholder [PHONE]';
        }
        
        // 3. Emoji density
        $emojiCount = preg_match_all('/[\x{1F600}-\x{1F64F}]/u', $message, $matches);
        $density = $emojiCount / max(1, strlen($message) / 100);
        if ($density > 5) {
            $score += $this->riskWeights['excessive_emojis'];
            $flags[] = 'excessive_emojis';
            $suggestions[] = 'Reduce emojis to 1-2 per message';
        }
        
        // 4. Commercial language
        $commercialTerms = ['buy', 'sale', 'discount', 'offer', 'order', 'shop', 'price', 'cost'];
        $commercialCount = 0;
        foreach ($commercialTerms as $term) {
            $commercialCount += preg_match_all('/\\b' . preg_quote($term, '/') . '\\b/i', $message);
        }
        if ($commercialCount > 2) {
            $score += $this->riskWeights['commercial_language'] * min($commercialCount, 3);
            $flags[] = 'commercial_language';
            $suggestions[] = 'Reduce commercial language; focus on value and service';
        }
        
        // 5. Urgency phrases
        $urgencyTerms = ['limited', 'hurry', 'today only', 'last chance', 'offer ends'];
        foreach ($urgencyTerms as $term) {
            if (stripos($message, $term) !== false) {
                $score += $this->riskWeights['urgency_phrases'];
                $flags[] = 'urgency_phrases';
                $suggestions[] = 'Remove urgency phrases (perceived as spam)';
                break;
            }
        }
        
        // 6. URL shorteners
        if (preg_match('/bit\\.ly|tinyurl|short\\.link|goo\\.gl/', $message)) {
            $score += $this->riskWeights['url_shorteners'];
            $flags[] = 'url_shorteners';
            $suggestions[] = 'Remove shortened URLs or use full links';
        }
        
        // 7. Excessive caps
        $capsCount = strlen(preg_replace('/[^A-Z]/', '', $message));
        $capsRatio = $capsCount / max(1, strlen($message));
        if ($capsRatio > 0.3) {
            $score += $this->riskWeights['excessive_caps'];
            $flags[] = 'excessive_caps';
            $suggestions[] = 'Reduce capital letters to <20% of message';
        }
        
        // 8. Formatting characters
        if (preg_match('/[━─═]{3,}/', $message)) {
            $score += $this->riskWeights['formatting_chars'];
            $flags[] = 'formatting_chars';
            $suggestions[] = 'Remove decorative formatting characters';
        }
        
        // Determine risk level
        $riskLevel = $this->getRiskLevel($score);
        
        return [
            'risk_score' => min(100, $score),
            'risk_level' => $riskLevel,
            'flags' => $flags,
            'suggestions' => $suggestions,
            'requires_approval' => $riskLevel === 'high',
            'can_auto_fix' => $riskLevel !== 'critical'
        ];
    }
    
    public function createSafeVersion(string $originalMessage, array $analysis): string {
        if ($analysis['risk_score'] < 30) {
            return $originalMessage;
        }
        
        $safe = $originalMessage;
        
        // Remove formatting lines
        $safe = preg_replace('/[━─═]{3,}.*/', '', $safe);
        
        // Replace phone numbers with placeholder
        $safe = preg_replace('/\\b[6-9]\\d{9}\\b/', '[CONTACT]', $safe);
        
        // Replace direct prices with ranges
        $safe = preg_replace('/[₹₹]\\s*\\d+/', '₹XX', $safe);
        
        // Reduce emojis to max 2
        $emojiPattern = '/[\x{1F600}-\x{1F64F}]/u';
        $emojiCount = preg_match_all($emojiPattern, $safe, $matches);
        if ($emojiCount > 2) {
            // Keep first 2 emojis, remove others
            $safe = preg_replace_callback($emojiPattern, function($matches) use (&$keep) {
                static $kept = 0;
                if ($kept < 2) {
                    $kept++;
                    return $matches[0];
                }
                return '';
            }, $safe);
        }
        
        // Lowercase excessive caps (keep first letter of sentences)
        $safe = preg_replace_callback('/([.!?]\\s+)([A-Z]+)/', function($matches) {
            return $matches[1] . ucfirst(strtolower($matches[2]));
        }, $safe);
        
        // Truncate if too long (>500 chars for cold send, >800 for reply)
        if (strlen($safe) > 500) {
            $safe = substr($safe, 0, 450) . "\n\nReply 'MENU' for complete details";
        }
        
        return trim($safe);
    }
    
    private function getRiskLevel(int $score): string {
        if ($score < 20) return 'safe';
        if ($score < 40) return 'low';
        if ($score < 60) return 'medium';
        if ($score < 80) return 'high';
        return 'critical';
    }
}
