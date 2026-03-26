<?php

namespace App\Services;

use App\Models\ErpCustomer;
use App\Jobs\PushOperaAccountToErpJob;
use App\Services\Erp\BusinessCentralApiClient;
use App\Services\Opera\OperaOhipClient;
use Illuminate\Support\Facades\Log;

class SyncOneCustomerService
{
    public function __construct(
        protected BusinessCentralApiClient $erpClient,
        protected OperaOhipClient $operaClient,
        protected PayloadAuditService $payloadAudit
    ) {
    }

    public function syncOne(array $item, bool $pushToErp = true, string $direction = 'ERP_TO_OPERA', bool $useProvidedProfileId = false): void
    {
        $erpNumber = $item['erp_number'];
        $payload = $item['payload'] ?? [];
        if (is_array($payload) && ! empty($item['custom_fields'])) {
            $payload['_mapped'] = $item['custom_fields'];
        }
   Log::channel('integration')->info('Opera company profile payload', [
            'payload' => $payload,
            
        ]);
        $customer = ErpCustomer::updateOrCreate(
            ['erp_number' => $erpNumber],
            [
                'status' => $item['status'] ?? null,
                'name' => $item['name'],
                'account_type' => $item['account_type'] ?? null,
                'active' => $item['active'] ?? true,
                'blocked' => $item['blocked'] ?? false,
                'ar_number' => $item['ar_number'] ?? null,
                'address_1' => $item['address_1'] ?? null,
                'address_2' => $item['address_2'] ?? null,
                'vat_registration_no' => $item['vat_registration_no'] ?? null,
                'phone' => $item['phone'] ?? null,
                'email' => $item['email'] ?? null,
                'country' => $item['country'] ?? null,
                'post_code' => $item['post_code'] ?? null,
                'payment_terms_code' => $item['payment_terms_code'] ?? null,
                'property' => $item['property'] ?? null,
                'catalog_code' => $item['catalog_code'] ?? null,
                'system_modified_at' => $item['system_modified_at'] ?? null,
                'payload' => $payload,
                'has_credit' => $item['has_credit'] ?? false,
                'credit_limit' => $item['credit_limit'] ?? null,
                'last_modified_at' => isset($item['last_modified_at']) ? $item['last_modified_at'] : null,
            ]
        );
        $providedOperaProfileId = null;
        if ($useProvidedProfileId && ! empty($item['opera_profile_id'])) {
            $providedOperaProfileId = (string) $item['opera_profile_id'];
            $customer->opera_profile_id = $providedOperaProfileId;
            $customer->save();
        }

        if ($providedOperaProfileId !== null) {
            $operaProfileId = $providedOperaProfileId;
        } else {
            $operaProfileId = $this->operaClient->syncCompanyProfile($item, $customer->opera_profile_id);
            if ($operaProfileId !== null && $operaProfileId !== '') {
                $customer->opera_profile_id = $operaProfileId;
                $customer->save();
            }
        }

        Log::channel('integration')->info('Opera company profile resolved', [
            'erp_number' => $erpNumber,
            'profile_id' => $customer->opera_profile_id,
        ]);

        $item['opera_profile_id'] = $customer->opera_profile_id;
        if (! empty($customer->opera_profile_id)) {
            $addressId = null;
            $addressId = $this->operaClient->getProfileArAddressId($customer->opera_profile_id);
            if (empty($addressId)) {
                $addressId = $this->operaClient->ensureProfileArAddress($customer->opera_profile_id, $item);
            }
            $item['opera_ar_address_id'] = $addressId;
            $item['opera_ar_address_type'] = 'AR ADDRESS';
            if (empty($item['opera_ar_address_id'])) {
                throw new \RuntimeException('Opera profile has no AR ADDRESS id; cannot create AR account. profile_id=' . $customer->opera_profile_id);
            }
        }

        $operaPayload = $this->operaClient->buildArPayload($item);
        $operaUpdatePayload = $this->operaClient->buildArUpdatePayload($item);
        $operaAccountNumber = $customer->opera_account_number;
        $operaAccountNoToPush = $operaPayload['criteria']['accountNo'] ?? null;
        $useMinimalUpdate = strtoupper((string) config('opera.ar_account_update_method', 'PUT')) === 'PATCH';
        $rawArNumber = $item['ar_number'] ?? null;
        $storedAccountNo = null;
        if (is_string($operaAccountNumber) && $operaAccountNumber !== '') {
            // If stored value is numeric only, it's likely an accountId (not valid for update URL).
            $storedAccountNo = ctype_digit($operaAccountNumber) ? null : $operaAccountNumber;
        }

            $accountNo = $operaPayload['criteria']['accountNo'] ?? null;
            $hotelId = $operaPayload['criteria']['hotelId'] ?? null;
            $payloadForUpdate = $useMinimalUpdate ? $operaUpdatePayload : $operaPayload;
            $auditAction = null;
            $auditPayload = null;
            $auditAccountId = null;
            $auditAccountNo = null;
            $auditHotelId = is_string($hotelId) ? $hotelId : null;

            $existingAccount = null;
            if (is_string($accountNo) && $accountNo !== '') {
                $existingAccount = $this->operaClient->findArAccountByAccountNo($accountNo, is_string($hotelId) ? $hotelId : null);
                if ($existingAccount === null && is_string($rawArNumber) && $rawArNumber !== '' && $rawArNumber !== $accountNo) {
                    $existingAccount = $this->operaClient->findArAccountByAccountNo($rawArNumber, is_string($hotelId) ? $hotelId : null);
                }
                if ($existingAccount === null && is_string($storedAccountNo) && $storedAccountNo !== '' && $storedAccountNo !== $accountNo) {
                    $existingAccount = $this->operaClient->findArAccountByAccountNo($storedAccountNo, is_string($hotelId) ? $hotelId : null);
                }
            } elseif (is_string($storedAccountNo) && $storedAccountNo !== '') {
                $existingAccount = $this->operaClient->findArAccountByAccountNo($storedAccountNo, is_string($hotelId) ? $hotelId : null);
            }

            if ($existingAccount && ! empty($existingAccount['hotel_id'])) {
                $accountHotel = (string) $existingAccount['hotel_id'];
                if (isset($payloadForUpdate['criteria'])) {
                    $payloadForUpdate['criteria']['hotelId'] = $accountHotel;
                }
                if (isset($payloadForUpdate['accountDetails'])) {
                    $payloadForUpdate['accountDetails']['hotelId'] = $accountHotel;
                }
                if (isset($payloadForUpdate['hotelId'])) {
                    $payloadForUpdate['hotelId'] = $accountHotel;
                }
            }

            if ($existingAccount && ! empty($existingAccount['account_id'])) {
                $accountIdForUpdate = (string) $existingAccount['account_id'];
                $accountNoForUpdate = ! empty($existingAccount['account_no']) ? (string) $existingAccount['account_no'] : null;
                Log::channel('integration')->info('Opera AR update target resolved (account_id)', [
                    'erp_number' => $erpNumber,
                    'account_id' => $accountIdForUpdate,
                    'account_no' => $accountNoForUpdate,
                    'hotel_id' => $existingAccount['hotel_id'] ?? null,
                ]);
                $auditAction = 'update';
                $auditPayload = $payloadForUpdate;
                $auditAccountId = $accountIdForUpdate;
                $auditAccountNo = $accountNoForUpdate;
                $auditHotelId = $existingAccount['hotel_id'] ?? $auditHotelId;
                $operaAccountNumber = $this->operaClient->updateArAccount($accountIdForUpdate, $payloadForUpdate, $accountNoForUpdate) ?? $accountIdForUpdate;
                if ($accountNoForUpdate !== null && $accountNoForUpdate !== '') {
                    $operaAccountNoToPush = $accountNoForUpdate;
                }
            } elseif ($existingAccount && ! empty($existingAccount['account_no'])) {
                $accountNoForUpdate = (string) $existingAccount['account_no'];
                Log::channel('integration')->info('Opera AR update target resolved (account_no)', [
                    'erp_number' => $erpNumber,
                    'account_no' => $accountNoForUpdate,
                    'hotel_id' => $existingAccount['hotel_id'] ?? null,
                ]);
                $auditAction = 'update';
                $auditPayload = $payloadForUpdate;
                $auditAccountNo = $accountNoForUpdate;
                $auditHotelId = $existingAccount['hotel_id'] ?? $auditHotelId;
                $operaAccountNumber = $this->operaClient->updateArAccount($accountNoForUpdate, $payloadForUpdate) ?? $accountNoForUpdate;
                $operaAccountNoToPush = $accountNoForUpdate;
            } elseif (is_string($accountNo) && $accountNo !== '') {
                $auditAction = 'create';
                $auditPayload = $operaPayload;
                $auditAccountNo = $accountNo;
                $operaAccountNumber = $this->operaClient->createArAccount($operaPayload);
            } else {
                $auditAction = 'create';
                $auditPayload = $operaPayload;
                $operaAccountNumber = $this->operaClient->createArAccount($operaPayload);
            }

            $accountNoToStore = is_string($operaAccountNoToPush) && $operaAccountNoToPush !== '' ? $operaAccountNoToPush : $operaAccountNumber;
            $customer->update([
                'opera_account_number' => $accountNoToStore,
                'last_modified_at' => now(),
            ]);

        $this->payloadAudit->logMetadata($direction, $erpNumber, 'success', 200, null);
        if (is_array($auditPayload)) {
            $this->payloadAudit->storeEncrypted($direction, $erpNumber, 'success', 200, [
                'action' => $auditAction,
                'erp_number' => $erpNumber,
                'account_id' => $auditAccountId,
                'account_no' => $auditAccountNo,
                'hotel_id' => $auditHotelId,
                'payload' => $auditPayload,
            ]);
        }

        $erpPushEnabled = (bool) config('erp.push_enabled', true);
        if ($pushToErp && $erpPushEnabled && is_string($operaAccountNoToPush) && $operaAccountNoToPush !== '') {
            PushOperaAccountToErpJob::dispatch($erpNumber, $operaAccountNoToPush);
        }
    }
}
