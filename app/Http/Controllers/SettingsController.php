<?php

namespace App\Http\Controllers;

use App\Models\Country;
use App\Models\Currency;
use App\Models\InsuranceCompany;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;

class SettingsController extends Controller
{
    /**
     * Settings page: third party vendors only.
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        $canAccessSettings = (int) ($user->business_id ?? 0) === 1
            || in_array('View Insurance Companies', $user->permissions ?? [])
            || in_array('Manage Service Charges', $user->permissions ?? []);

        if (! $canAccessSettings) {
            return redirect()->route('dashboard')->with('error', 'You do not have permission to view settings.');
        }

        // Backward compatibility for old tab URL.
        if ($request->get('tab') === 'currencies') {
            return redirect()->route('settings.countries.index');
        }

        $canViewVendorChargesTab = (int) ($user->business_id ?? 0) === 1
            || in_array('Manage Service Charges', $user->permissions ?? [])
            || in_array('View Insurance Companies', $user->permissions ?? []);

        $allowedTabs = ['insurance-companies', 'vendor-service-charges'];
        $activeTab = $request->query('tab', 'insurance-companies');
        if (! in_array($activeTab, $allowedTabs, true)) {
            $activeTab = 'insurance-companies';
        }
        if ($activeTab === 'vendor-service-charges' && ! $canViewVendorChargesTab) {
            $activeTab = 'insurance-companies';
        }

        $insuranceCompanies = $activeTab === 'insurance-companies'
            ? InsuranceCompany::with('business')
                ->latest()
                ->paginate(15)
                ->appends(['tab' => 'insurance-companies'])
            : new LengthAwarePaginator([], 0, 15, 1, [
                'path' => $request->url(),
                'query' => $request->query(),
                'pageName' => 'page',
            ]);

        return view('settings.index', compact('insuranceCompanies', 'activeTab', 'canViewVendorChargesTab'));
    }

    /**
     * Countries and exchange rates page (separate from vendors settings).
     */
    public function countriesIndex()
    {
        $user = Auth::user();
        if ((int) ($user->business_id ?? 0) !== 1) {
            abort(403);
        }

        $countries = Country::latest()->paginate(15);
        $editingCountry = null;
        $editId = (int) request()->query('edit', 0);
        if ($editId > 0) {
            $editingCountry = Country::find($editId);
        }

        return view('settings.countries', [
            'countries' => $countries,
            'editingCountry' => $editingCountry,
            'countryOptions' => $this->countryOptions(),
            'currencyOptions' => $this->currencyOptions(),
        ]);
    }

    public function storeCountry(Request $request)
    {
        $user = Auth::user();
        if ((int) ($user->business_id ?? 0) !== 1) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'iso_code' => 'required|string|max:10|unique:countries,iso_code',
            'currency_code' => 'required|string|max:10',
            'exchange_rate_to_usd' => 'required|numeric|min:0.000001',
        ]);

        $currencyCode = strtoupper(trim($validated['currency_code']));
        $currency = Currency::firstOrCreate(
            ['code' => $currencyCode],
            ['name' => $currencyCode, 'symbol' => $currencyCode]
        );

        Country::create([
            'name' => $validated['name'],
            'iso_code' => strtoupper(trim($validated['iso_code'])),
            'currency_id' => $currency->id,
            'currency_code' => $currencyCode,
            'exchange_rate_to_usd' => $validated['exchange_rate_to_usd'],
        ]);

        return redirect()->route('settings.countries.index')
            ->with('success', 'Country saved successfully.');
    }

    public function updateCountry(Request $request, Country $country)
    {
        $user = Auth::user();
        if ((int) ($user->business_id ?? 0) !== 1) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'iso_code' => 'required|string|max:10|unique:countries,iso_code,'.$country->id,
            'currency_code' => 'required|string|max:10',
            'exchange_rate_to_usd' => 'required|numeric|min:0.000001',
        ]);

        $currencyCode = strtoupper(trim($validated['currency_code']));
        $currency = Currency::firstOrCreate(
            ['code' => $currencyCode],
            ['name' => $currencyCode, 'symbol' => $currencyCode]
        );

        $country->update([
            'name' => $validated['name'],
            'iso_code' => strtoupper(trim($validated['iso_code'])),
            'currency_id' => $currency->id,
            'currency_code' => $currencyCode,
            'exchange_rate_to_usd' => $validated['exchange_rate_to_usd'],
        ]);

        return redirect()->route('settings.countries.index')
            ->with('success', 'Country updated successfully.');
    }

    public function destroyCountry(Country $country)
    {
        $user = Auth::user();
        if ((int) ($user->business_id ?? 0) !== 1) {
            abort(403);
        }

        $country->delete();

        return redirect()->route('settings.countries.index')
            ->with('success', 'Country deleted successfully.');
    }

    private function currencyOptions(): array
    {
        $savedCodes = Currency::query()
            ->orderBy('code')
            ->pluck('code')
            ->map(fn ($code) => strtoupper((string) $code))
            ->toArray();

        // All unique currency codes from the comprehensive country list
        $countryListCurrencies = array_unique(array_column($this->countryOptions(), 'currency_code'));

        // Merge saved codes with currency codes from country list
        $allCurrencies = array_values(array_unique(array_merge($savedCodes, $countryListCurrencies)));

        sort($allCurrencies);

        return $allCurrencies;
    }

    private function countryOptions(): array
    {
        return [
            ['name' => 'Afghanistan', 'iso_code' => 'AF', 'currency_code' => 'AFN'],
            ['name' => 'Albania', 'iso_code' => 'AL', 'currency_code' => 'ALL'],
            ['name' => 'Algeria', 'iso_code' => 'DZ', 'currency_code' => 'DZD'],
            ['name' => 'Andorra', 'iso_code' => 'AD', 'currency_code' => 'EUR'],
            ['name' => 'Angola', 'iso_code' => 'AO', 'currency_code' => 'AOA'],
            ['name' => 'Argentina', 'iso_code' => 'AR', 'currency_code' => 'ARS'],
            ['name' => 'Armenia', 'iso_code' => 'AM', 'currency_code' => 'AMD'],
            ['name' => 'Australia', 'iso_code' => 'AU', 'currency_code' => 'AUD'],
            ['name' => 'Austria', 'iso_code' => 'AT', 'currency_code' => 'EUR'],
            ['name' => 'Azerbaijan', 'iso_code' => 'AZ', 'currency_code' => 'AZN'],
            ['name' => 'Bahamas', 'iso_code' => 'BS', 'currency_code' => 'BSD'],
            ['name' => 'Bahrain', 'iso_code' => 'BH', 'currency_code' => 'BHD'],
            ['name' => 'Bangladesh', 'iso_code' => 'BD', 'currency_code' => 'BDT'],
            ['name' => 'Barbados', 'iso_code' => 'BB', 'currency_code' => 'BBD'],
            ['name' => 'Belarus', 'iso_code' => 'BY', 'currency_code' => 'BYN'],
            ['name' => 'Belgium', 'iso_code' => 'BE', 'currency_code' => 'EUR'],
            ['name' => 'Belize', 'iso_code' => 'BZ', 'currency_code' => 'BZD'],
            ['name' => 'Benin', 'iso_code' => 'BJ', 'currency_code' => 'XOF'],
            ['name' => 'Bhutan', 'iso_code' => 'BT', 'currency_code' => 'BTN'],
            ['name' => 'Bolivia', 'iso_code' => 'BO', 'currency_code' => 'BOB'],
            ['name' => 'Bosnia and Herzegovina', 'iso_code' => 'BA', 'currency_code' => 'BAM'],
            ['name' => 'Botswana', 'iso_code' => 'BW', 'currency_code' => 'BWP'],
            ['name' => 'Brazil', 'iso_code' => 'BR', 'currency_code' => 'BRL'],
            ['name' => 'Brunei', 'iso_code' => 'BN', 'currency_code' => 'BND'],
            ['name' => 'Bulgaria', 'iso_code' => 'BG', 'currency_code' => 'BGN'],
            ['name' => 'Burkina Faso', 'iso_code' => 'BF', 'currency_code' => 'XOF'],
            ['name' => 'Burundi', 'iso_code' => 'BI', 'currency_code' => 'BIF'],
            ['name' => 'Cambodia', 'iso_code' => 'KH', 'currency_code' => 'KHR'],
            ['name' => 'Cameroon', 'iso_code' => 'CM', 'currency_code' => 'XAF'],
            ['name' => 'Canada', 'iso_code' => 'CA', 'currency_code' => 'CAD'],
            ['name' => 'Cape Verde', 'iso_code' => 'CV', 'currency_code' => 'CVE'],
            ['name' => 'Central African Republic', 'iso_code' => 'CF', 'currency_code' => 'XAF'],
            ['name' => 'Chad', 'iso_code' => 'TD', 'currency_code' => 'XAF'],
            ['name' => 'Chile', 'iso_code' => 'CL', 'currency_code' => 'CLP'],
            ['name' => 'China', 'iso_code' => 'CN', 'currency_code' => 'CNY'],
            ['name' => 'Colombia', 'iso_code' => 'CO', 'currency_code' => 'COP'],
            ['name' => 'Comoros', 'iso_code' => 'KM', 'currency_code' => 'KMF'],
            ['name' => 'Congo', 'iso_code' => 'CG', 'currency_code' => 'XAF'],
            ['name' => 'Costa Rica', 'iso_code' => 'CR', 'currency_code' => 'CRC'],
            ['name' => 'Côte d\'Ivoire', 'iso_code' => 'CI', 'currency_code' => 'XOF'],
            ['name' => 'Croatia', 'iso_code' => 'HR', 'currency_code' => 'EUR'],
            ['name' => 'Cuba', 'iso_code' => 'CU', 'currency_code' => 'CUP'],
            ['name' => 'Cyprus', 'iso_code' => 'CY', 'currency_code' => 'EUR'],
            ['name' => 'Czech Republic', 'iso_code' => 'CZ', 'currency_code' => 'CZK'],
            ['name' => 'Denmark', 'iso_code' => 'DK', 'currency_code' => 'DKK'],
            ['name' => 'Djibouti', 'iso_code' => 'DJ', 'currency_code' => 'DJF'],
            ['name' => 'Dominica', 'iso_code' => 'DM', 'currency_code' => 'XCD'],
            ['name' => 'Dominican Republic', 'iso_code' => 'DO', 'currency_code' => 'DOP'],
            ['name' => 'Ecuador', 'iso_code' => 'EC', 'currency_code' => 'USD'],
            ['name' => 'Egypt', 'iso_code' => 'EG', 'currency_code' => 'EGP'],
            ['name' => 'El Salvador', 'iso_code' => 'SV', 'currency_code' => 'SVC'],
            ['name' => 'Equatorial Guinea', 'iso_code' => 'GQ', 'currency_code' => 'XAF'],
            ['name' => 'Eritrea', 'iso_code' => 'ER', 'currency_code' => 'ERN'],
            ['name' => 'Estonia', 'iso_code' => 'EE', 'currency_code' => 'EUR'],
            ['name' => 'Ethiopia', 'iso_code' => 'ET', 'currency_code' => 'ETB'],
            ['name' => 'Fiji', 'iso_code' => 'FJ', 'currency_code' => 'FJD'],
            ['name' => 'Finland', 'iso_code' => 'FI', 'currency_code' => 'EUR'],
            ['name' => 'France', 'iso_code' => 'FR', 'currency_code' => 'EUR'],
            ['name' => 'Gabon', 'iso_code' => 'GA', 'currency_code' => 'XAF'],
            ['name' => 'Gambia', 'iso_code' => 'GM', 'currency_code' => 'GMD'],
            ['name' => 'Georgia', 'iso_code' => 'GE', 'currency_code' => 'GEL'],
            ['name' => 'Germany', 'iso_code' => 'DE', 'currency_code' => 'EUR'],
            ['name' => 'Ghana', 'iso_code' => 'GH', 'currency_code' => 'GHS'],
            ['name' => 'Greece', 'iso_code' => 'GR', 'currency_code' => 'EUR'],
            ['name' => 'Grenada', 'iso_code' => 'GD', 'currency_code' => 'XCD'],
            ['name' => 'Guatemala', 'iso_code' => 'GT', 'currency_code' => 'GTQ'],
            ['name' => 'Guinea', 'iso_code' => 'GN', 'currency_code' => 'GNF'],
            ['name' => 'Guinea-Bissau', 'iso_code' => 'GW', 'currency_code' => 'XOF'],
            ['name' => 'Guyana', 'iso_code' => 'GY', 'currency_code' => 'GYD'],
            ['name' => 'Haiti', 'iso_code' => 'HT', 'currency_code' => 'HTG'],
            ['name' => 'Honduras', 'iso_code' => 'HN', 'currency_code' => 'HNL'],
            ['name' => 'Hong Kong', 'iso_code' => 'HK', 'currency_code' => 'HKD'],
            ['name' => 'Hungary', 'iso_code' => 'HU', 'currency_code' => 'HUF'],
            ['name' => 'Iceland', 'iso_code' => 'IS', 'currency_code' => 'ISK'],
            ['name' => 'India', 'iso_code' => 'IN', 'currency_code' => 'INR'],
            ['name' => 'Indonesia', 'iso_code' => 'ID', 'currency_code' => 'IDR'],
            ['name' => 'Iran', 'iso_code' => 'IR', 'currency_code' => 'IRR'],
            ['name' => 'Iraq', 'iso_code' => 'IQ', 'currency_code' => 'IQD'],
            ['name' => 'Ireland', 'iso_code' => 'IE', 'currency_code' => 'EUR'],
            ['name' => 'Israel', 'iso_code' => 'IL', 'currency_code' => 'ILS'],
            ['name' => 'Italy', 'iso_code' => 'IT', 'currency_code' => 'EUR'],
            ['name' => 'Jamaica', 'iso_code' => 'JM', 'currency_code' => 'JMD'],
            ['name' => 'Japan', 'iso_code' => 'JP', 'currency_code' => 'JPY'],
            ['name' => 'Jordan', 'iso_code' => 'JO', 'currency_code' => 'JOD'],
            ['name' => 'Kazakhstan', 'iso_code' => 'KZ', 'currency_code' => 'KZT'],
            ['name' => 'Kenya', 'iso_code' => 'KE', 'currency_code' => 'KES'],
            ['name' => 'Kiribati', 'iso_code' => 'KI', 'currency_code' => 'AUD'],
            ['name' => 'Kosovo', 'iso_code' => 'XK', 'currency_code' => 'EUR'],
            ['name' => 'Kuwait', 'iso_code' => 'KW', 'currency_code' => 'KWD'],
            ['name' => 'Kyrgyzstan', 'iso_code' => 'KG', 'currency_code' => 'KGS'],
            ['name' => 'Laos', 'iso_code' => 'LA', 'currency_code' => 'LAK'],
            ['name' => 'Latvia', 'iso_code' => 'LV', 'currency_code' => 'EUR'],
            ['name' => 'Lebanon', 'iso_code' => 'LB', 'currency_code' => 'LBP'],
            ['name' => 'Lesotho', 'iso_code' => 'LS', 'currency_code' => 'LSL'],
            ['name' => 'Liberia', 'iso_code' => 'LR', 'currency_code' => 'LRD'],
            ['name' => 'Libya', 'iso_code' => 'LY', 'currency_code' => 'LYD'],
            ['name' => 'Liechtenstein', 'iso_code' => 'LI', 'currency_code' => 'CHF'],
            ['name' => 'Lithuania', 'iso_code' => 'LT', 'currency_code' => 'EUR'],
            ['name' => 'Luxembourg', 'iso_code' => 'LU', 'currency_code' => 'EUR'],
            ['name' => 'Macao', 'iso_code' => 'MO', 'currency_code' => 'MOP'],
            ['name' => 'Madagascar', 'iso_code' => 'MG', 'currency_code' => 'MGA'],
            ['name' => 'Malawi', 'iso_code' => 'MW', 'currency_code' => 'MWK'],
            ['name' => 'Malaysia', 'iso_code' => 'MY', 'currency_code' => 'MYR'],
            ['name' => 'Maldives', 'iso_code' => 'MV', 'currency_code' => 'MVR'],
            ['name' => 'Mali', 'iso_code' => 'ML', 'currency_code' => 'XOF'],
            ['name' => 'Malta', 'iso_code' => 'MT', 'currency_code' => 'EUR'],
            ['name' => 'Marshall Islands', 'iso_code' => 'MH', 'currency_code' => 'USD'],
            ['name' => 'Mauritania', 'iso_code' => 'MR', 'currency_code' => 'MRU'],
            ['name' => 'Mauritius', 'iso_code' => 'MU', 'currency_code' => 'MUR'],
            ['name' => 'Mexico', 'iso_code' => 'MX', 'currency_code' => 'MXN'],
            ['name' => 'Micronesia', 'iso_code' => 'FM', 'currency_code' => 'USD'],
            ['name' => 'Moldova', 'iso_code' => 'MD', 'currency_code' => 'MDL'],
            ['name' => 'Monaco', 'iso_code' => 'MC', 'currency_code' => 'EUR'],
            ['name' => 'Mongolia', 'iso_code' => 'MN', 'currency_code' => 'MNT'],
            ['name' => 'Montenegro', 'iso_code' => 'ME', 'currency_code' => 'EUR'],
            ['name' => 'Morocco', 'iso_code' => 'MA', 'currency_code' => 'MAD'],
            ['name' => 'Mozambique', 'iso_code' => 'MZ', 'currency_code' => 'MZN'],
            ['name' => 'Myanmar', 'iso_code' => 'MM', 'currency_code' => 'MMK'],
            ['name' => 'Namibia', 'iso_code' => 'NA', 'currency_code' => 'NAD'],
            ['name' => 'Nauru', 'iso_code' => 'NR', 'currency_code' => 'AUD'],
            ['name' => 'Nepal', 'iso_code' => 'NP', 'currency_code' => 'NPR'],
            ['name' => 'Netherlands', 'iso_code' => 'NL', 'currency_code' => 'EUR'],
            ['name' => 'New Zealand', 'iso_code' => 'NZ', 'currency_code' => 'NZD'],
            ['name' => 'Nicaragua', 'iso_code' => 'NI', 'currency_code' => 'NIO'],
            ['name' => 'Niger', 'iso_code' => 'NE', 'currency_code' => 'XOF'],
            ['name' => 'Nigeria', 'iso_code' => 'NG', 'currency_code' => 'NGN'],
            ['name' => 'North Korea', 'iso_code' => 'KP', 'currency_code' => 'KPW'],
            ['name' => 'North Macedonia', 'iso_code' => 'MK', 'currency_code' => 'MKD'],
            ['name' => 'Norway', 'iso_code' => 'NO', 'currency_code' => 'NOK'],
            ['name' => 'Oman', 'iso_code' => 'OM', 'currency_code' => 'OMR'],
            ['name' => 'Pakistan', 'iso_code' => 'PK', 'currency_code' => 'PKR'],
            ['name' => 'Palau', 'iso_code' => 'PW', 'currency_code' => 'USD'],
            ['name' => 'Palestine', 'iso_code' => 'PS', 'currency_code' => 'ILS'],
            ['name' => 'Panama', 'iso_code' => 'PA', 'currency_code' => 'PAB'],
            ['name' => 'Papua New Guinea', 'iso_code' => 'PG', 'currency_code' => 'PGK'],
            ['name' => 'Paraguay', 'iso_code' => 'PY', 'currency_code' => 'PYG'],
            ['name' => 'Peru', 'iso_code' => 'PE', 'currency_code' => 'PEN'],
            ['name' => 'Philippines', 'iso_code' => 'PH', 'currency_code' => 'PHP'],
            ['name' => 'Poland', 'iso_code' => 'PL', 'currency_code' => 'PLN'],
            ['name' => 'Portugal', 'iso_code' => 'PT', 'currency_code' => 'EUR'],
            ['name' => 'Qatar', 'iso_code' => 'QA', 'currency_code' => 'QAR'],
            ['name' => 'Romania', 'iso_code' => 'RO', 'currency_code' => 'RON'],
            ['name' => 'Russia', 'iso_code' => 'RU', 'currency_code' => 'RUB'],
            ['name' => 'Rwanda', 'iso_code' => 'RW', 'currency_code' => 'RWF'],
            ['name' => 'Saint Kitts and Nevis', 'iso_code' => 'KN', 'currency_code' => 'XCD'],
            ['name' => 'Saint Lucia', 'iso_code' => 'LC', 'currency_code' => 'XCD'],
            ['name' => 'Saint Vincent and the Grenadines', 'iso_code' => 'VC', 'currency_code' => 'XCD'],
            ['name' => 'Samoa', 'iso_code' => 'WS', 'currency_code' => 'WST'],
            ['name' => 'San Marino', 'iso_code' => 'SM', 'currency_code' => 'EUR'],
            ['name' => 'Sao Tome and Principe', 'iso_code' => 'ST', 'currency_code' => 'STN'],
            ['name' => 'Saudi Arabia', 'iso_code' => 'SA', 'currency_code' => 'SAR'],
            ['name' => 'Senegal', 'iso_code' => 'SN', 'currency_code' => 'XOF'],
            ['name' => 'Serbia', 'iso_code' => 'RS', 'currency_code' => 'RSD'],
            ['name' => 'Seychelles', 'iso_code' => 'SC', 'currency_code' => 'SCR'],
            ['name' => 'Sierra Leone', 'iso_code' => 'SL', 'currency_code' => 'SLL'],
            ['name' => 'Singapore', 'iso_code' => 'SG', 'currency_code' => 'SGD'],
            ['name' => 'Slovakia', 'iso_code' => 'SK', 'currency_code' => 'EUR'],
            ['name' => 'Slovenia', 'iso_code' => 'SI', 'currency_code' => 'EUR'],
            ['name' => 'Solomon Islands', 'iso_code' => 'SB', 'currency_code' => 'SBD'],
            ['name' => 'Somalia', 'iso_code' => 'SO', 'currency_code' => 'SOS'],
            ['name' => 'South Africa', 'iso_code' => 'ZA', 'currency_code' => 'ZAR'],
            ['name' => 'South Korea', 'iso_code' => 'KR', 'currency_code' => 'KRW'],
            ['name' => 'South Sudan', 'iso_code' => 'SS', 'currency_code' => 'SSP'],
            ['name' => 'Spain', 'iso_code' => 'ES', 'currency_code' => 'EUR'],
            ['name' => 'Sri Lanka', 'iso_code' => 'LK', 'currency_code' => 'LKR'],
            ['name' => 'Sudan', 'iso_code' => 'SD', 'currency_code' => 'SDG'],
            ['name' => 'Suriname', 'iso_code' => 'SR', 'currency_code' => 'SRD'],
            ['name' => 'Sweden', 'iso_code' => 'SE', 'currency_code' => 'SEK'],
            ['name' => 'Switzerland', 'iso_code' => 'CH', 'currency_code' => 'CHF'],
            ['name' => 'Syria', 'iso_code' => 'SY', 'currency_code' => 'SYP'],
            ['name' => 'Taiwan', 'iso_code' => 'TW', 'currency_code' => 'TWD'],
            ['name' => 'Tajikistan', 'iso_code' => 'TJ', 'currency_code' => 'TJS'],
            ['name' => 'Tanzania', 'iso_code' => 'TZ', 'currency_code' => 'TZS'],
            ['name' => 'Thailand', 'iso_code' => 'TH', 'currency_code' => 'THB'],
            ['name' => 'Timor-Leste', 'iso_code' => 'TL', 'currency_code' => 'USD'],
            ['name' => 'Togo', 'iso_code' => 'TG', 'currency_code' => 'XOF'],
            ['name' => 'Tonga', 'iso_code' => 'TO', 'currency_code' => 'TOP'],
            ['name' => 'Trinidad and Tobago', 'iso_code' => 'TT', 'currency_code' => 'TTD'],
            ['name' => 'Tunisia', 'iso_code' => 'TN', 'currency_code' => 'TND'],
            ['name' => 'Turkey', 'iso_code' => 'TR', 'currency_code' => 'TRY'],
            ['name' => 'Turkmenistan', 'iso_code' => 'TM', 'currency_code' => 'TMT'],
            ['name' => 'Tuvalu', 'iso_code' => 'TV', 'currency_code' => 'AUD'],
            ['name' => 'Uganda', 'iso_code' => 'UG', 'currency_code' => 'UGX'],
            ['name' => 'Ukraine', 'iso_code' => 'UA', 'currency_code' => 'UAH'],
            ['name' => 'United Arab Emirates', 'iso_code' => 'AE', 'currency_code' => 'AED'],
            ['name' => 'United Kingdom', 'iso_code' => 'GB', 'currency_code' => 'GBP'],
            ['name' => 'United States', 'iso_code' => 'US', 'currency_code' => 'USD'],
            ['name' => 'Uruguay', 'iso_code' => 'UY', 'currency_code' => 'UYU'],
            ['name' => 'Uzbekistan', 'iso_code' => 'UZ', 'currency_code' => 'UZS'],
            ['name' => 'Vanuatu', 'iso_code' => 'VU', 'currency_code' => 'VUV'],
            ['name' => 'Vatican City', 'iso_code' => 'VA', 'currency_code' => 'EUR'],
            ['name' => 'Venezuela', 'iso_code' => 'VE', 'currency_code' => 'VES'],
            ['name' => 'Vietnam', 'iso_code' => 'VN', 'currency_code' => 'VND'],
            ['name' => 'Yemen', 'iso_code' => 'YE', 'currency_code' => 'YER'],
            ['name' => 'Zambia', 'iso_code' => 'ZM', 'currency_code' => 'ZMW'],
            ['name' => 'Zimbabwe', 'iso_code' => 'ZW', 'currency_code' => 'ZWL'],
        ];
    }
}
