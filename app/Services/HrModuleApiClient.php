<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Collection;

class HrModuleApiClient
{
    private Client $http;

    public function __construct()
    {
        $this->http = new Client([
            'base_uri'        => rtrim(config('services.hr_module.url'), '/') . '/api/v1/hr/',
            'headers'         => [
                'X-HR-API-Key' => config('services.hr_module.api_key'),
                'Accept'       => 'application/json',
            ],
            'timeout'         => 5,
            'connect_timeout' => 3,
        ]);
    }

    public function stats(?int $businessId = null): array
    {
        return $this->get('stats', $businessId ? ['business_id' => $businessId] : []);
    }

    public function employees(?int $businessId = null, array $params = []): array
    {
        return $this->get('employees', array_merge(
            $businessId ? ['business_id' => $businessId] : [],
            $params
        ));
    }

    public function employee(string $uuid): array
    {
        $response = $this->http->get("employees/{$uuid}");
        return json_decode($response->getBody()->getContents(), true);
    }

    public function attendanceSummary(?int $businessId = null, ?string $date = null): array
    {
        $params = [];
        if ($businessId) $params['business_id'] = $businessId;
        if ($date)       $params['date'] = $date;
        return $this->get('attendance/summary', $params);
    }

    public function attendanceRecords(?int $businessId = null, array $params = []): array
    {
        return $this->get('attendance/records', array_merge(
            $businessId ? ['business_id' => $businessId] : [],
            $params
        ));
    }

    public function payslips(?int $businessId = null, array $params = []): array
    {
        return $this->get('payslips', array_merge(
            $businessId ? ['business_id' => $businessId] : [],
            $params
        ));
    }

    public function navigation(): array
    {
        $result = $this->get('navigation');
        if (!is_array($result)) return [];
        // Accept either a flat array [{label,path},...] or a wrapped {items:[...]}
        return isset($result['items']) ? $result['items'] : $result;
    }

    public function get(string $endpoint, array $params = []): array
    {
        $response = $this->http->get($endpoint, ['query' => $params]);
        return json_decode($response->getBody()->getContents(), true);
    }
}
