<?php

namespace App\Services;

use App\Models\Client;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class ThirdPartyApiService
{
    protected $baseUrl;
    protected $timeout;

    public function __construct()
    {
        $this->baseUrl = config('services.third_party.api_url', env('THIRD_PARTY_API_URL', 'http://127.0.0.1:8001'));
        $this->timeout = config('services.third_party.timeout', 30);
    }

    /**
     * Register a business and user in the third-party system
     *
     * @param array $businessData
     * @param array $userData
     * @param int|null $connectToInsuranceCompanyId Optional: ID of insurance company to connect to
     * @return array|null
     */
    public function registerBusinessAndUser(array $businessData, array $userData, ?int $connectToInsuranceCompanyId = null): ?array
    {
        try {
            $payload = [
                // Business/Insurance Company data
                'name' => $businessData['name'] ?? '',
                'code' => $businessData['code'] ?? null,
                'email' => $businessData['email'] ?? '',
                'phone' => $businessData['phone'] ?? null,
                'country_id' => $businessData['country_id'] ?? null,
                'country_name' => $businessData['country_name'] ?? null,
                'country_iso_code' => $businessData['country_iso_code'] ?? null,
                'currency_code' => $businessData['currency_code'] ?? null,
                'exchange_rate_to_usd' => $businessData['exchange_rate_to_usd'] ?? null,
                'address' => $businessData['address'] ?? null,
                'head_office_address' => $businessData['head_office_address'] ?? $businessData['address'] ?? null,
                'postal_address' => $businessData['postal_address'] ?? null,
                'website' => $businessData['website'] ?? null,
                'description' => $businessData['description'] ?? null,
                'open_enrollment_enabled' => $businessData['open_enrollment_enabled'] ?? false,
                
                // User data
                'user_name' => $userData['name'] ?? '',
                'user_email' => $userData['email'] ?? '',
                'user_username' => $userData['username'] ?? '',
                'user_password' => $userData['password'] ?? '',
            ];
            
            // Add connection if provided
            if ($connectToInsuranceCompanyId) {
                $payload['connect_to_insurance_company_id'] = $connectToInsuranceCompanyId;
            }

            Log::info('ThirdPartyApiService: Registering business and user', [
                'business_name' => $payload['name'],
                'user_email' => $payload['user_email'],
                'country_name' => $payload['country_name'],
                'currency_code' => $payload['currency_code'],
                'open_enrollment_enabled' => $payload['open_enrollment_enabled'],
            ]);

            $response = Http::timeout($this->timeout)
                ->post("{$this->baseUrl}/api/v1/businesses/register", $payload);

            if ($response->successful()) {
                $data = $response->json();
                
                Log::info('ThirdPartyApiService: Business and user registered successfully', [
                    'business_id' => $data['data']['business']['id'] ?? null,
                    'user_id' => $data['data']['user']['id'] ?? null,
                ]);

                return $data['data'] ?? null;
            } else {
                $error = $response->json();
                Log::error('ThirdPartyApiService: Failed to register business and user', [
                    'status' => $response->status(),
                    'error' => $error,
                ]);

                throw new Exception(
                    $error['message'] ?? 'Failed to register business and user in third-party system',
                    $response->status()
                );
            }
        } catch (Exception $e) {
            Log::error('ThirdPartyApiService: Exception occurred', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Get business details from third-party system
     *
     * @param int $businessId
     * @param string|null $token
     * @return array|null
     */
    public function getBusiness(int $businessId, ?string $token = null): ?array
    {
        try {
            $request = Http::timeout($this->timeout);

            if ($token) {
                $request->withToken($token);
            }

            $response = $request->get("{$this->baseUrl}/api/v1/businesses/{$businessId}");

            if ($response->successful()) {
                $data = $response->json();
                return $data['data'] ?? null;
            }

            return null;
        } catch (Exception $e) {
            Log::error('ThirdPartyApiService: Failed to get business', [
                'business_id' => $businessId,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Check if business exists by name or email
     *
     * @param string $name
     * @param string $email
     * @return array|null Returns business data if exists, null otherwise
     */
    public function checkBusinessExists(string $name, string $email): ?array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->get("{$this->baseUrl}/api/v1/businesses/check", [
                    'name' => $name,
                    'email' => $email,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                if ($data['success'] && isset($data['data'])) {
                    return $data['data'];
                }
            }

            return null;
        } catch (Exception $e) {
            Log::error('ThirdPartyApiService: Failed to check if business exists', [
                'name' => $name,
                'email' => $email,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Check if user exists by email or username
     *
     * @param string $email
     * @param string|null $username
     * @return array|null Returns user data if exists, null otherwise
     */
    public function checkUserExists(string $email, ?string $username = null): ?array
    {
        try {
            $params = ['email' => $email];
            if ($username !== null) {
                $params['username'] = $username;
            }
            
            $response = Http::timeout($this->timeout)
                ->get("{$this->baseUrl}/api/v1/users/check", $params);

            if ($response->successful()) {
                $data = $response->json();
                if ($data['success'] && isset($data['exists']) && $data['exists'] === true) {
                    return $data['data'] ?? null;
                }
            }

            return null;
        } catch (Exception $e) {
            Log::error('ThirdPartyApiService: Failed to check if user exists', [
                'email' => $email,
                'username' => $username,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Create user for existing business
     *
     * @param int $businessId
     * @param array $userData
     * @return array|null
     */
    public function createUserForBusiness(int $businessId, array $userData): ?array
    {
        try {
            $payload = [
                'business_id' => $businessId,
                'user_name' => $userData['name'] ?? '',
                'user_email' => $userData['email'] ?? '',
                'user_username' => $userData['username'] ?? '',
                'user_password' => $userData['password'] ?? '',
            ];

            Log::info('ThirdPartyApiService: Creating user for existing business', [
                'business_id' => $businessId,
                'user_email' => $payload['user_email'],
            ]);

            $response = Http::timeout($this->timeout)
                ->post("{$this->baseUrl}/api/v1/businesses/{$businessId}/users", $payload);

            if ($response->successful()) {
                $data = $response->json();
                
                Log::info('ThirdPartyApiService: User created successfully for existing business', [
                    'business_id' => $businessId,
                    'user_id' => $data['data']['user']['id'] ?? null,
                ]);

                return $data['data'] ?? null;
            } else {
                $error = $response->json();
                Log::error('ThirdPartyApiService: Failed to create user for existing business', [
                    'status' => $response->status(),
                    'error' => $error,
                ]);

                throw new Exception(
                    $error['message'] ?? 'Failed to create user for existing business',
                    $response->status()
                );
            }
        } catch (Exception $e) {
            Log::error('ThirdPartyApiService: Exception occurred while creating user', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Login and get API token
     *
     * @param string $email
     * @param string $password
     * @return array|null
     */
    public function login(string $email, string $password): ?array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->post("{$this->baseUrl}/api/v1/auth/login", [
                    'email' => $email,
                    'password' => $password,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['data'] ?? null;
            }

            return null;
        } catch (Exception $e) {
            Log::error('ThirdPartyApiService: Failed to login', [
                'email' => $email,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Generate password reset token for a user and send email (handled by third-party system)
     *
     * @param string $email
     * @return array|null Returns the full response including success status
     */
    public function generatePasswordResetToken(string $email): ?array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->post("{$this->baseUrl}/api/v1/password/reset-token", [
                    'email' => $email,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                // Return the full response including success and data
                return $data;
            }

            Log::error('ThirdPartyApiService: Failed to generate password reset token', [
                'email' => $email,
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            return null;
        } catch (Exception $e) {
            Log::error('ThirdPartyApiService: Exception while generating password reset token', [
                'email' => $email,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Get insurance company by code from third-party system
     *
     * @param string $code 8-character alphanumeric third party vendor code
     * @return array|null
     */
    public function getInsuranceCompanyByCode(string $code): ?array
    {
        try {
            Log::info('ThirdPartyApiService: Getting insurance company by code', [
                'code' => $code,
            ]);

            $response = Http::timeout($this->timeout)
                ->get("{$this->baseUrl}/api/v1/businesses/by-code/{$code}");

            if ($response->successful()) {
                $data = $response->json();
                
                Log::info('ThirdPartyApiService: Insurance company found by code', [
                    'code' => $code,
                    'business_id' => $data['data']['business']['id'] ?? null,
                ]);

                return $data['data'] ?? null;
            } else {
                $error = $response->json();
                Log::warning('ThirdPartyApiService: Insurance company not found by code', [
                    'code' => $code,
                    'status' => $response->status(),
                    'error' => $error,
                ]);

                return null;
            }
        } catch (Exception $e) {
            Log::error('ThirdPartyApiService: Exception while getting insurance company by code', [
                'code' => $code,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Create a business connection in the third-party system
     *
     * @param int $insuranceCompanyId The insurance company to connect to
     * @param int $connectedBusinessId The business being connected
     * @return array|null Returns connection data if successful, null otherwise
     */
    public function createBusinessConnection(int $insuranceCompanyId, int $connectedBusinessId, ?string $connectedBusinessName = null): ?array
    {
        try {
            $payload = [
                'insurance_company_id' => $insuranceCompanyId,
                'connected_business_id' => $connectedBusinessId,
            ];
            
            if ($connectedBusinessName) {
                $payload['connected_business_name'] = $connectedBusinessName;
            }

            Log::info('ThirdPartyApiService: Creating business connection', [
                'insurance_company_id' => $insuranceCompanyId,
                'connected_business_id' => $connectedBusinessId,
            ]);

            $response = Http::timeout($this->timeout)
                ->post("{$this->baseUrl}/api/v1/businesses/connections", $payload);

            if ($response->successful()) {
                $data = $response->json();
                
                Log::info('ThirdPartyApiService: Business connection created successfully', [
                    'insurance_company_id' => $insuranceCompanyId,
                    'connected_business_id' => $connectedBusinessId,
                    'connection_id' => $data['data']['connection_id'] ?? null,
                ]);

                return $data['data'] ?? null;
            } else {
                $error = $response->json();
                Log::error('ThirdPartyApiService: Failed to create business connection', [
                    'status' => $response->status(),
                    'error' => $error,
                ]);

                return null;
            }
        } catch (Exception $e) {
            Log::error('ThirdPartyApiService: Exception while creating business connection', [
                'insurance_company_id' => $insuranceCompanyId,
                'connected_business_id' => $connectedBusinessId,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Verify if a policy number exists for a given insurance company
     *
     * @param int $insuranceCompanyId
     * @param string $policyNumber
     * @param string|null $name Optional: Full name for tolerance-based verification
     * @param string|null $dateOfBirth Optional: Date of birth for tolerance-based verification
     * @return array|null Returns policy data if exists, null otherwise
     */
    public function verifyPolicyNumber(int $insuranceCompanyId, string $policyNumber, ?string $name = null, ?string $dateOfBirth = null, ?string $servicesCategory = null, array $extraParams = []): ?array
    {
        try {
            Log::info('=== Kashtre: verifyPolicyNumber START ===', [
                'insurance_company_id' => $insuranceCompanyId,
                'policy_number' => $policyNumber,
                'has_name' => !empty($name),
                'has_dob' => !empty($dateOfBirth),
                'name' => $name,
                'date_of_birth' => $dateOfBirth,
                'services_category' => $servicesCategory,
                'extra_params' => $extraParams,
            ]);

            // Build query parameters if name or DOB are provided
            $queryParams = $extraParams;
            if ($name) {
                $queryParams['name'] = $name;
            }
            if ($dateOfBirth) {
                $queryParams['date_of_birth'] = $dateOfBirth;
            }
            if ($servicesCategory) {
                $queryParams['services_category'] = $servicesCategory;
            }

            $url = "{$this->baseUrl}/api/v1/policies/verify/{$insuranceCompanyId}/{$policyNumber}";
            if (!empty($queryParams)) {
                $url .= '?' . http_build_query($queryParams);
            }

            Log::info('Kashtre: Sending API request', [
                'url' => $url,
                'method' => 'GET',
                'query_params' => $queryParams,
            ]);

            $response = Http::timeout($this->timeout)
                ->get($url);

            Log::info('Kashtre: Received API response', [
                'status_code' => $response->status(),
                'response_headers' => $response->headers(),
            ]);

            $data = $response->json();

            if ($response->successful()) {
                Log::info('Kashtre: Policy number verified - SUCCESS', [
                    'insurance_company_id' => $insuranceCompanyId,
                    'policy_number' => $policyNumber,
                    'response_status_code' => $response->status(),
                    'response_data' => $data,
                    'exists' => $data['exists'] ?? false,
                    'verification_status' => $data['verification_status'] ?? null,
                    'verification_method' => $data['verification_method'] ?? null,
                    'has_warnings' => !empty($data['warnings']),
                    'warnings' => $data['warnings'] ?? [],
                ]);

                return $data;
            } else {
                Log::warning('Kashtre: Policy number verification FAILED', [
                    'insurance_company_id' => $insuranceCompanyId,
                    'policy_number' => $policyNumber,
                    'response_status_code' => $response->status(),
                    'response_body' => $response->body(),
                    'response_data' => $data,
                ]);

                // Return the error body so callers can surface the exact message
                return $data;
            }
        } catch (Exception $e) {
            Log::error('Kashtre: Exception while verifying policy number', [
                'insurance_company_id' => $insuranceCompanyId,
                'policy_number' => $policyNumber,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        } finally {
            Log::info('=== Kashtre: verifyPolicyNumber END ===');
        }
    }

    /**
     * Verify client identity using name and date of birth (alternative verification)
     * Optionally includes services_category so coverage checks can be applied earlier.
     *
     * @param int $insuranceCompanyId
     * @param array $verificationData Array containing: name, date_of_birth
     * @return array|null Returns verification result with policy data if verified, null otherwise
     */
    public function verifyAlternativeIdentity(int $insuranceCompanyId, array $verificationData): ?array
    {
        try {
            // Only send name, date_of_birth, and optional services_category (remove visit_id and other fields)
            $data = [
                'name' => $verificationData['name'] ?? null,
                'date_of_birth' => $verificationData['date_of_birth'] ?? null,
            ];
            if (!empty($verificationData['services_category'])) {
                $data['services_category'] = $verificationData['services_category'];
            }
            
            // Remove null values
            $data = array_filter($data, function($value) {
                return $value !== null && $value !== '';
            });
            
            Log::info('=== Kashtre: verifyAlternativeIdentity START ===', [
                'insurance_company_id' => $insuranceCompanyId,
                'original_verification_data' => $verificationData,
                'filtered_data' => $data,
                'has_name' => !empty($data['name']),
                'has_dob' => !empty($data['date_of_birth']),
            ]);

            $url = "{$this->baseUrl}/api/v1/policies/verify/{$insuranceCompanyId}";
            
            Log::info('Kashtre: Sending alternative verification API request', [
                'url' => $url,
                'method' => 'POST',
                'payload' => $data,
            ]);

            $response = Http::timeout($this->timeout)
                ->post($url, $data);
            
            Log::info('Kashtre: Received alternative verification API response', [
                'status_code' => $response->status(),
                'response_headers' => $response->headers(),
            ]);

            if ($response->successful()) {
                $responseData = $response->json();
                
                Log::info('Kashtre: Alternative verification SUCCESS', [
                    'insurance_company_id' => $insuranceCompanyId,
                    'response_status_code' => $response->status(),
                    'response_data' => $responseData,
                    'verification_method' => $responseData['verification_method'] ?? null,
                    'verification_status' => $responseData['verification_status'] ?? null,
                    'exists' => $responseData['exists'] ?? false,
                    'warnings' => $responseData['warnings'] ?? [],
                ]);

                return $responseData;
            } else {
                $error = $response->json();
                Log::warning('Kashtre: Alternative verification FAILED', [
                    'insurance_company_id' => $insuranceCompanyId,
                    'response_status_code' => $response->status(),
                    'response_body' => $response->body(),
                    'response_data' => $error,
                ]);

                return $error;
            }
        } catch (Exception $e) {
            Log::error('Kashtre: Exception while verifying alternative identity', [
                'insurance_company_id' => $insuranceCompanyId,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        } finally {
            Log::info('=== Kashtre: verifyAlternativeIdentity END ===');
        }
    }

    /**
     * Verify identity using visit ID
     *
     * @param int $insuranceCompanyId
     * @param string $visitId
     * @param array $additionalData Optional additional verification data
     * @return array|null Returns verification result with policy data if verified, null otherwise
     */
    public function verifyVisitIdentity(int $insuranceCompanyId, string $visitId, array $additionalData = []): ?array
    {
        try {
            $data = array_merge(['visit_id' => $visitId], $additionalData);

            Log::info('ThirdPartyApiService: Verifying visit identity', [
                'insurance_company_id' => $insuranceCompanyId,
                'visit_id' => $visitId,
            ]);

            $response = Http::timeout($this->timeout)
                ->post("{$this->baseUrl}/api/v1/policies/verify-visit/{$insuranceCompanyId}", $data);

            if ($response->successful()) {
                $result = $response->json();
                
                Log::info('ThirdPartyApiService: Visit identity verified', [
                    'insurance_company_id' => $insuranceCompanyId,
                    'visit_id' => $visitId,
                    'verification_method' => $result['verification_method'] ?? null,
                    'verification_status' => $result['verification_status'] ?? null,
                ]);

                return $result;
            } else {
                $error = $response->json();
                Log::warning('ThirdPartyApiService: Visit identity verification failed', [
                    'insurance_company_id' => $insuranceCompanyId,
                    'visit_id' => $visitId,
                    'status' => $response->status(),
                    'error' => $error,
                ]);

                return $error;
            }
        } catch (Exception $e) {
            Log::error('ThirdPartyApiService: Exception while verifying visit identity', [
                'insurance_company_id' => $insuranceCompanyId,
                'visit_id' => $visitId,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Create client account in third-party system
     *
     * @param int $clientId Client ID in third-party system
     * @param int $insuranceCompanyId Insurance company ID
     * @return array|null Returns account data if successful, null otherwise
     */
    public function createClientAccount(int $clientId, int $insuranceCompanyId): ?array
    {
        try {
            Log::info('ThirdPartyApiService: Creating client account', [
                'client_id' => $clientId,
                'insurance_company_id' => $insuranceCompanyId,
            ]);

            $response = Http::timeout($this->timeout)
                ->post("{$this->baseUrl}/api/v1/clients/{$clientId}/account", [
                    'insurance_company_id' => $insuranceCompanyId,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                
                Log::info('ThirdPartyApiService: Client account created successfully', [
                    'client_id' => $clientId,
                    'account_number' => $data['data']['account']['account_number'] ?? null,
                ]);

                return $data['data'] ?? null;
            } else {
                $error = $response->json();
                Log::warning('ThirdPartyApiService: Failed to create client account', [
                    'client_id' => $clientId,
                    'status' => $response->status(),
                    'error' => $error,
                ]);

                return null;
            }
        } catch (Exception $e) {
            Log::error('ThirdPartyApiService: Exception while creating client account', [
                'client_id' => $clientId,
                'insurance_company_id' => $insuranceCompanyId,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Create a payment responsibility payment in the third-party system
     *
     * @param array $paymentData
     * @return array|null
     */
    public function createPaymentResponsibilityPayment(array $paymentData): ?array
    {
        try {
            Log::info('ThirdPartyApiService: Creating payment responsibility payment', [
                'payment_data' => $paymentData,
            ]);

            $response = Http::timeout($this->timeout)
                ->post("{$this->baseUrl}/api/v1/payments/responsibility", $paymentData);

            if ($response->successful()) {
                $data = $response->json();
                
                Log::info('ThirdPartyApiService: Payment responsibility payment created successfully', [
                    'payment_id' => $data['data']['id'] ?? null,
                    'reference' => $paymentData['payment_reference'] ?? null,
                ]);

                return $data;
            } else {
                $error = $response->json();
                Log::warning('ThirdPartyApiService: Failed to create payment responsibility payment', [
                    'status' => $response->status(),
                    'error' => $error,
                    'payment_data' => $paymentData,
                ]);

                return null;
            }
        } catch (Exception $e) {
            Log::error('ThirdPartyApiService: Exception while creating payment responsibility payment', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'payment_data' => $paymentData,
            ]);

            return null;
        }
    }

    /**
     * Request invoice authorization from third-party (insurance company).
     * Returns client_total and insurance_total for the invoice.
     *
     * @param array $payload kashtre_invoice_id, invoice_number, insurance_company_id (third-party ID), policy_number, total_amount, deductible_remaining, copay_contributes_to_deductible, coinsurance_contributes_to_deductible, items
     * @return array|null { success, authorization_reference, client_total, insurance_total, breakdown } or null on failure
     */
    public function requestInvoiceAuthorization(array $payload): ?array
    {
        $url = "{$this->baseUrl}/api/v1/invoice-authorization/request";
        try {
            Log::info('[Kashtre->ThirdParty] Invoice authorization request', [
                'url' => $url,
                'kashtre_invoice_id' => $payload['kashtre_invoice_id'] ?? null,
                'invoice_number' => $payload['invoice_number'] ?? null,
                'insurance_company_id' => $payload['insurance_company_id'] ?? null,
                'policy_number' => $payload['policy_number'] ?? null,
                'total_amount' => $payload['total_amount'] ?? null,
                'deductible_remaining' => $payload['deductible_remaining'] ?? null,
                'items_count' => isset($payload['items']) && is_array($payload['items']) ? count($payload['items']) : 0,
            ]);

            $response = Http::timeout($this->timeout)
                ->acceptJson()
                ->post($url, $payload);

            Log::info('[Kashtre->ThirdParty] Invoice authorization response', [
                'http_status' => $response->status(),
                'body_preview' => strlen($response->body()) > 500 ? substr($response->body(), 0, 500) . '...' : $response->body(),
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if (!empty($data['success']) && isset($data['client_total'], $data['insurance_total'])) {
                    Log::info('[Kashtre->ThirdParty] Invoice authorization success', [
                        'authorization_reference' => $data['authorization_reference'] ?? null,
                        'client_total' => $data['client_total'],
                        'insurance_total' => $data['insurance_total'],
                        'breakdown' => $data['breakdown'] ?? null,
                    ]);
                    return $data;
                }
                Log::warning('[Kashtre->ThirdParty] Invoice authorization response missing expected fields', [
                    'success' => $data['success'] ?? null,
                    'has_client_total' => isset($data['client_total']),
                    'has_insurance_total' => isset($data['insurance_total']),
                    'raw' => $data,
                ]);
                // Return structured failure so caller can surface message
                return [
                    'success' => false,
                    'message' => $data['message'] ?? 'Invoice authorization response missing expected fields.',
                    'raw' => $data,
                ];
            }

            $error = $response->json();
            $body = $response->body();
            $fallbackMessage = 'Invoice authorization failed.';
            if (is_string($body) && $body !== '') {
                $trimmed = trim($body);
                $fallbackMessage = strlen($trimmed) > 180 ? substr($trimmed, 0, 180) . '...' : $trimmed;
            }
            Log::warning('[Kashtre->ThirdParty] Invoice authorization HTTP error', [
                'status' => $response->status(),
                'body' => $body,
                'error' => $error,
            ]);
            // Return structured failure instead of null so UI can show reason
            return [
                'success' => false,
                'message' => $error['message'] ?? $fallbackMessage,
                'http_status' => $response->status(),
            ];
        } catch (Exception $e) {
            Log::error('[Kashtre->ThirdParty] Invoice authorization exception', [
                'message' => $e->getMessage(),
                'url' => $url,
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    /**
     * Record a client-portion payment in the third-party system.
     * This is called after mobile money payment succeeds in Kashtre for an insurance invoice.
     *
     * @param array $payload Required: insurance_company_id (third-party ID), policy_number, amount, payment_reference.
     *                       Optional: kashtre_invoice_id, authorization_reference, connected_business_id,
     *                                 payment_method, mobile_money_number, payment_date.
     * @return array{success: bool, message?: string}
     */
    public function recordClientPortionPayment(array $payload): array
    {
        $url = "{$this->baseUrl}/api/v1/payments/record-client-portion";

        try {
            Log::info('[Kashtre->ThirdParty] Recording client-portion payment', [
                'url' => $url,
                'insurance_company_id' => $payload['insurance_company_id'] ?? null,
                'policy_number' => $payload['policy_number'] ?? null,
                'amount' => $payload['amount'] ?? null,
                'payment_reference' => $payload['payment_reference'] ?? null,
                'kashtre_invoice_id' => $payload['kashtre_invoice_id'] ?? null,
                'authorization_reference' => $payload['authorization_reference'] ?? null,
            ]);

            $response = Http::timeout($this->timeout)
                ->acceptJson()
                ->post($url, $payload);

            if ($response->successful()) {
                $body = $response->json();
                Log::info('[Kashtre->ThirdParty] Client-portion payment recorded successfully', [
                    'status' => $response->status(),
                    'body' => $body,
                ]);

                return ['success' => true];
            }

            Log::warning('[Kashtre->ThirdParty] Failed to record client-portion payment', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            $error = $response->json();

            return [
                'success' => false,
                'message' => $error['message'] ?? ('Third-party returned HTTP ' . $response->status()),
            ];
        } catch (Exception $e) {
            Log::error('[Kashtre->ThirdParty] Exception while recording client-portion payment', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get insurance company registration settings from third-party
     */
    public function getInsuranceCompanySettings(int $insuranceCompanyId): ?array
    {
        try {
            Log::info('Kashtre: Fetching insurance company settings', [
                'insurance_company_id' => $insuranceCompanyId,
            ]);

            // Fetch full settings including open enrollment
            $url = "{$this->baseUrl}/api/v1/businesses/{$insuranceCompanyId}/settings";

            $response = Http::timeout($this->timeout)->get($url);

            if ($response->successful()) {
                $data = $response->json();
                
                // Now fetch registration desk settings separately
                try {
                    $regUrl = "{$this->baseUrl}/api/v1/insurance-companies/{$insuranceCompanyId}/settings";
                    $regResponse = Http::timeout($this->timeout)->get($regUrl);
                    
                    if ($regResponse->successful()) {
                        $regData = $regResponse->json();
                        // Merge registration settings into data
                        if (isset($data['data'])) {
                            $data['data']['show_policy_details_at_registration'] = $regData['show_policy_details_at_registration'] ?? true;
                            $data['data']['policy_details_to_display_at_registration'] = $regData['policy_details_to_display_at_registration'] ?? [];
                            $data['data']['visit_authorization_period_days'] = $regData['visit_authorization_period_days'] ?? 7;
                        }
                    }
                } catch (Exception $e) {
                    Log::warning('Kashtre: Could not fetch registration desk settings', ['error' => $e->getMessage()]);
                    // Continue with main settings only
                }
                
                Log::info('Kashtre: Insurance company settings fetched successfully', [
                    'insurance_company_id' => $insuranceCompanyId,
                ]);
                
                return $data['data'] ?? $data;
            }

            Log::warning('Kashtre: Failed to fetch insurance company settings', [
                'insurance_company_id' => $insuranceCompanyId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        } catch (Exception $e) {
            Log::error('Kashtre: Exception while fetching insurance company settings', [
                'insurance_company_id' => $insuranceCompanyId,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    /**
     * Sync client to third-party vendor system when registered via open enrollment
     * 
     * @param \App\Models\Client $client
     * @return array|null
     */
    public function syncClientToVendor($client, ?int $thirdPartyInsuranceCompanyId = null): ?array
    {
        try {
            // When called for multi-vendor OE, the caller provides the third-party insurance company ID
            // directly and we skip the registered_via_open_enrollment guard.
            $insuranceCompanyId = $thirdPartyInsuranceCompanyId ?? $client->insurance_company_id;

            if (!$insuranceCompanyId) {
                Log::info('API: syncClientToVendor - Skipping (no insurance company ID)', [
                    'kashtre_client_id' => $client->client_id,
                ]);
                return null;
            }

            // For the legacy single-vendor path (no explicit ID provided), enforce the OE guard.
            if ($thirdPartyInsuranceCompanyId === null && !$client->registered_via_open_enrollment) {
                Log::info('API: syncClientToVendor - Skipping (not open enrollment)', [
                    'kashtre_client_id' => $client->client_id,
                    'is_open_enrollment' => $client->registered_via_open_enrollment,
                ]);
                return null;
            }

            Log::info('API: syncClientToVendor - Starting sync request', [
                'kashtre_client_id' => $client->client_id,
                'insurance_company_id' => $insuranceCompanyId,
                'endpoint' => "{$this->baseUrl}/api/v1/clients/sync",
            ]);

            $payload = [
                'kashtre_client_id' => $client->client_id,
                'insurance_company_id' => $insuranceCompanyId,
                'first_name' => $client->first_name,
                'surname' => $client->surname,
                'other_names' => $client->other_names,
                'phone_number' => $client->phone_number,
                'email' => $client->email,
                'date_of_birth' => $client->date_of_birth ? $client->date_of_birth->toDateString() : null,
                'gender' => strtolower($client->sex ?? ''),
                'marital_status' => $client->marital_status,
                'occupation' => $client->occupation,
                'nationality' => $client->nationality,
                'policy_number' => $client->policy_number,
                // For multi-vendor OE (explicit ID provided), the client IS via open enrollment
                // even though registered_via_open_enrollment on the model is false (multi-vendor flag)
                'registered_via_open_enrollment' => $thirdPartyInsuranceCompanyId !== null ? true : (bool)$client->registered_via_open_enrollment,
            ];

            Log::debug('API: syncClientToVendor - Payload', ['payload' => $payload]);

            $response = Http::timeout($this->timeout)
                ->post("{$this->baseUrl}/api/v1/clients/sync", $payload);

            Log::info('API: syncClientToVendor - Response received', [
                'kashtre_client_id' => $client->client_id,
                'status_code' => $response->status(),
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                Log::info('API: syncClientToVendor - Success', [
                    'kashtre_client_id' => $client->client_id,
                    'vendor_success' => $data['success'] ?? false,
                    'vendor_message' => $data['message'] ?? 'N/A',
                    'vendor_client_id' => $data['data']['client']['id'] ?? null,
                ]);

                return $data;
            } else {
                Log::warning('API: syncClientToVendor - Failed', [
                    'kashtre_client_id' => $client->client_id,
                    'status_code' => $response->status(),
                    'response_body' => $response->body(),
                ]);

                return null;
            }
        } catch (Exception $e) {
            Log::error('API: syncClientToVendor - Exception', [
                'kashtre_client_id' => $client->client_id ?? null,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
            ]);

            return null;
        }
    }

    /**
     * Register authorized visit on third-party vendor system
     * 
     * @param $client
     * @param string $visitId
     * @param string $visitDate
     * @param string|null $expiryAt
     * @param string|null $servicesCategory
     * @return array|null
     */
    public function registerAuthorizedVisit($client, string $visitId, string $visitDate, ?string $expiryAt = null, ?string $servicesCategory = null, $vendorId = null): ?array
    {
        try {
            // For multi-vendor: use provided vendorId
            // For legacy single-vendor: use client->insurance_company_id
            $insuranceCompanyId = $vendorId ?? $client->insurance_company_id;
            
            if (!$insuranceCompanyId) {
                Log::info('API: registerAuthorizedVisit - Skipping (no insurance)', [
                    'kashtre_client_id' => $client->client_id,
                ]);
                return null;
            }

            Log::info('API: registerAuthorizedVisit - Starting visit registration', [
                'kashtre_client_id' => $client->client_id,
                'visit_id' => $visitId,
                'insurance_company_id' => $insuranceCompanyId,
                'vendor_id' => $vendorId,
                'is_multi_vendor' => $vendorId !== null,
                'endpoint' => "{$this->baseUrl}/api/v1/authorized-visits/register",
            ]);

            $payload = [
                'kashtre_client_id' => $client->client_id,
                'visit_id' => $visitId,
                'insurance_company_id' => $insuranceCompanyId,
                'vendor_id' => $vendorId, // Multi-vendor field (null for legacy single-vendor)
                'visit_date' => $visitDate,
                'expiry_at' => $expiryAt,
                'services_category' => $servicesCategory ?? $client->services_category,
                'notes' => "Visit registered via kashtre on " . now()->toDateTimeString(),
            ];

            Log::debug('API: registerAuthorizedVisit - Payload', ['payload' => $payload]);

            $response = Http::timeout($this->timeout)
                ->post("{$this->baseUrl}/api/v1/authorized-visits/register", $payload);

            Log::info('API: registerAuthorizedVisit - Response received', [
                'kashtre_client_id' => $client->client_id,
                'visit_id' => $visitId,
                'status_code' => $response->status(),
            ]);

            if ($response->successful()) {
                $data = $response->json();

                if (($data['success'] ?? false) && isset($data['data']['visit']) && $client instanceof Client) {
                    try {
                        $this->persistVisitAuthorizationSession($client, $data, (int) $insuranceCompanyId);
                    } catch (\Throwable $e) {
                        Log::warning('ThirdPartyApiService: could not persist visit authorization session on client', [
                            'kashtre_client_id' => $client->client_id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                Log::info('API: registerAuthorizedVisit - Success', [
                    'kashtre_client_id' => $client->client_id,
                    'visit_id' => $visitId,
                    'vendor_success' => $data['success'] ?? false,
                    'vendor_message' => $data['message'] ?? 'N/A',
                    'vendor_visit_id' => $data['data']['visit']['id'] ?? null,
                ]);

                return $data;
            } else {
                Log::warning('API: registerAuthorizedVisit - Failed', [
                    'kashtre_client_id' => $client->client_id,
                    'visit_id' => $visitId,
                    'status_code' => $response->status(),
                    'response_body' => $response->body(),
                ]);

                return null;
            }
        } catch (Exception $e) {
            Log::error('API: registerAuthorizedVisit - Exception', [
                'kashtre_client_id' => $client->client_id ?? null,
                'visit_id' => $visitId ?? null,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
            ]);

            return null;
        }
    }

    /**
     * Store insurer-issued session code + expiry on the Kashtre client (keyed by remote insurance_company_id sent to the vendor API).
     */
    protected function persistVisitAuthorizationSession(Client $client, array $apiResponse, int $remoteInsuranceCompanyId): void
    {
        $visit = $apiResponse['data']['visit'] ?? null;
        if (!$visit || empty($visit['session_code'])) {
            return;
        }

        $key = (string) $remoteInsuranceCompanyId;
        $sessions = $client->visit_authorization_sessions;
        if (!is_array($sessions)) {
            $sessions = [];
        }

        $sessions[$key] = [
            'session_code' => $visit['session_code'],
            'session_expires_at' => $visit['session_expires_at'] ?? null,
            'third_party_authorized_visit_id' => $visit['id'] ?? null,
            'visit_id' => $visit['visit_id'] ?? null,
            'visit_authorization_period_days' => $visit['visit_authorization_period_days'] ?? null,
            'stored_at' => now()->toIso8601String(),
        ];

        $client->visit_authorization_sessions = $sessions;
        $client->saveQuietly();
    }
}
