<?php

return [
    /*
    |----------------------------------------------------------------------
    | ERP customer field mapping
    |----------------------------------------------------------------------
    | These are the source keys used by ErpCustomerMapper to read ERP records
    | (including uploaded CSV/JSON). Edit via Admin -> Field Mapping.
    */
    'erp_customer' => [
        'erp_number' => ['number', 'no', 'id'],
        'ar_number' => ['arNumber', 'accountNo'],
        'name' => ['name', 'displayName'],
        'status' => ['status'],
        'active' => ['active'],
        'blocked' => ['blocked'],
        'account_type' => ['accountType', 'type'],
        'address_1' => ['address'],
        'address_2' => ['address2'],
        'city' => ['city', 'cityName'],
        'state' => ['state', 'stateName'],
        'country' => ['country'],
        'post_code' => ['postCode', 'postalCode'],
        'phone' => ['phoneNo', 'phone', 'telephone'],
        'email' => ['email', 'emailAddress'],
        'vat_registration_no' => ['vatRegistrationNo', 'vatRegitrationNo', 'tax1No'],
        'has_credit' => ['creditApproved', 'hasCredit'],
        'credit_limit' => ['creditLimit', 'creditLimitLCY'],
        'payment_terms_code' => ['paymentTermsCode', 'paymentTremsCode'],
        'property' => ['property', 'hotelId', 'HotelID'],
        'last_modified_at' => ['lastDateModified', 'systemModifiedAt', 'lastModifiedDateTime'],
        'system_modified_at' => ['systemModifiedAt'],
        'catalog_code' => ['catalogCode'],
        'restricted_reason' => ['restrictedReason', 'blockedReason'],
    ],
];
