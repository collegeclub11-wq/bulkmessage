<?php
// backend-php/config/routes.php

return [
    'POST/api/auth/login'            => ['AuthController', 'login'],
    'POST/api/auth/register'         => ['AuthController', 'register'],
    'GET/api/auth/me'                => ['AuthController', 'me'],

    'GET/api/campaigns'              => ['CampaignController', 'index'],
    'POST/api/campaigns'             => ['CampaignController', 'store'],
    'GET/api/campaigns/show'         => ['CampaignController', 'show'],
    'POST/api/campaigns/pause'       => ['CampaignController', 'pause'],
    'POST/api/campaigns/resume'      => ['CampaignController', 'resume'],

    'GET/api/templates'              => ['TemplateController', 'index'],
    'POST/api/templates'             => ['TemplateController', 'store'],
    'POST/api/templates/update'      => ['TemplateController', 'update'],
    'DELETE/api/templates'           => ['TemplateController', 'delete'],

    'GET/api/contacts'               => ['ContactController', 'index'],
    'POST/api/contacts'              => ['ContactController', 'store'],
    'POST/api/contacts/upload'       => ['ContactController', 'upload'],
    'GET/api/contacts/groups'        => ['ContactController', 'listGroups'],

    'GET/api/whatsapp/sessions'      => ['WhatsAppController', 'sessions'],
    'POST/api/whatsapp/sessions'     => ['WhatsAppController', 'createSession'],
    'GET/api/whatsapp/qr'            => ['WhatsAppController', 'getQR'],

    'GET/api/reports/dashboard'      => ['CampaignController', 'dashboardStats'],
    'GET/api/reports/export'         => ['CampaignController', 'exportReport'],

    // Super Admin Routes
    'GET/api/superadmin/tenants'         => ['SuperAdminController', 'listTenants'],
    'POST/api/superadmin/tenants'        => ['SuperAdminController', 'createTenant'],
    'POST/api/superadmin/tenants/status' => ['SuperAdminController', 'updateTenantStatus'],
    'POST/api/superadmin/tenants/reset-password' => ['SuperAdminController', 'resetTenantPassword'],
    'GET/api/superadmin/keys'            => ['SuperAdminController', 'listKeys'],
    'POST/api/superadmin/keys'           => ['SuperAdminController', 'createKey'],
    'POST/api/superadmin/keys/revoke'    => ['SuperAdminController', 'revokeKey']
];
?>
