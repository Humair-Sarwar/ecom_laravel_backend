<?php

namespace App\Http\Controllers\SystemUser;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\BusinessGroup;
use App\Models\User;
use App\Models\Role;
use App\Models\Country;
use App\Models\City;
use App\Services\TenantSchemaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class BusinessController extends Controller
{
    /**
     * Display a listing of businesses with tab counts and filters.
     */
    public function index(Request $request)
    {
        $tab = $request->query('tab', 'all'); // 'all', 'active', 'blocked', 'deleted'
        $search = trim($request->query('search', ''));
        $ownerId = $request->query('owner_id', '');
        $schemaName = $request->query('schema_name', '');
        $packageLevel = $request->query('package_level', '');
        $viewMode = $request->query('view_mode', 'businesses'); // 'businesses' or 'by_owner'
        $perPage = (int) $request->query('per_page', 10);

        // Calculate counts for all tabs
        $allCount = Business::count();
        $activeCount = Business::where('status', 'active')->count();
        $blockedCount = Business::where('status', 'blocked')->count();
        $deletedCount = Business::onlyTrashed()->count();

        // Build base query
        if ($tab === 'deleted') {
            $query = Business::onlyTrashed()->with(['owner', 'role', 'country', 'city', 'group']);
        } else {
            $query = Business::with(['owner', 'role', 'country', 'city', 'group']);

            if ($tab === 'active') {
                $query->where('status', 'active');
            } elseif ($tab === 'blocked') {
                $query->where('status', 'blocked');
            }
        }

        // Apply filters
        if (!empty($ownerId) && $ownerId !== 'all') {
            $query->where('owner_id', $ownerId);
        }

        if (!empty($schemaName) && $schemaName !== 'all') {
            $query->where('schema_name', $schemaName);
        }

        if (!empty($packageLevel) && $packageLevel !== 'all') {
            $query->where('package_level', $packageLevel);
        }

        if (!empty($search)) {
            $pattern = "%{$search}%";
            $query->where(function ($q) use ($pattern) {
                $q->where('name', 'ilike', $pattern)
                  ->orWhere('branch_name', 'ilike', $pattern)
                  ->orWhere('email', 'ilike', $pattern)
                  ->orWhere('mobile_no', 'ilike', $pattern)
                  ->orWhere('whatsapp_no', 'ilike', $pattern)
                  ->orWhere('schema_name', 'ilike', $pattern)
                  ->orWhereRaw(
                      "EXISTS (SELECT 1 FROM users WHERE users.id::text = businesses.owner_id AND (users.first_name ILIKE ? OR users.last_name ILIKE ? OR users.email ILIKE ? OR CONCAT(COALESCE(users.first_name, ''), ' ', COALESCE(users.last_name, '')) ILIKE ?))",
                      [$pattern, $pattern, $pattern, $pattern]
                  );
            });
        }

        // Check if grouped by owner view is requested
        if ($viewMode === 'by_owner') {
            $businesses = $query->orderBy('created_at', 'desc')->get();
            $grouped = $businesses->groupBy('owner_id')->map(function ($items, $key) {
                $owner = $items->first()->owner;
                return [
                    'owner_id' => $key,
                    'owner' => $owner,
                    'businesses_count' => $items->count(),
                    'businesses' => $items,
                ];
            })->values();

            return response()->json([
                'status' => true,
                'view_mode' => 'by_owner',
                'counts' => [
                    'all' => $allCount,
                    'active' => $activeCount,
                    'blocked' => $blockedCount,
                    'deleted' => $deletedCount,
                ],
                'data' => $grouped,
            ]);
        }

        // Standard paginated listing
        $paginated = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'status' => true,
            'view_mode' => 'businesses',
            'counts' => [
                'all' => $allCount,
                'active' => $activeCount,
                'blocked' => $blockedCount,
                'deleted' => $deletedCount,
            ],
            'data' => $paginated->items(),
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
            'per_page' => $paginated->perPage(),
            'total' => $paginated->total(),
        ]);
    }

    /**
     * Get form options for create and update dropdowns.
     * Supports scoped live backend search (`scope=owners|groups|countries|cities`)
     * to avoid re-querying schemas and unrelated lookup tables while typing.
     */
    public function formOptions(Request $request)
    {
        $scope = trim((string) $request->query('scope', ''));
        $ownerSearch = mb_strtolower(trim((string) $request->query('owner_search', '')));
        $groupSearch = mb_strtolower(trim((string) $request->query('group_search', '')));
        $countrySearch = mb_strtolower(trim((string) $request->query('country_search', '')));
        $citySearch = mb_strtolower(trim((string) $request->query('city_search', '')));
        $countryId = $request->query('country_id', '');

        $queryOwners = function () use ($ownerSearch) {
            $q = User::where('user_type', '!=', 3)
                ->select('id', 'first_name', 'last_name', 'email', 'phone', 'business_name', 'user_type');

            if ($ownerSearch !== '') {
                $like = "%{$ownerSearch}%";
                $q->where(function ($sub) use ($like) {
                    $sub->whereRaw('LOWER(COALESCE(first_name, \'\')) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(COALESCE(last_name, \'\')) LIKE ?', [$like])
                        ->orWhereRaw("LOWER(CONCAT(COALESCE(first_name,''), ' ', COALESCE(last_name,''))) LIKE ?", [$like])
                        ->orWhereRaw('LOWER(COALESCE(email, \'\')) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(COALESCE(phone, \'\')) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(COALESCE(business_name, \'\')) LIKE ?', [$like]);
                });
            }

            return $q->orderBy('first_name', 'asc')->limit(50)->get();
        };

        $queryGroups = function () use ($groupSearch) {
            $q = BusinessGroup::where('status', 'active')
                ->select('id', 'name', 'owner_id');

            if ($groupSearch !== '') {
                $like = "%{$groupSearch}%";
                $q->whereRaw('LOWER(COALESCE(name, \'\')) LIKE ?', [$like]);
            }

            return $q->orderBy('name', 'asc')->limit(50)->get();
        };

        $queryCountries = function () use ($countrySearch) {
            $q = Country::select('id', 'name', 'nice_name', 'iso', 'phone_code', 'currency');

            if ($countrySearch !== '') {
                $like = "%{$countrySearch}%";
                $q->where(function ($sub) use ($like) {
                    $sub->whereRaw('LOWER(COALESCE(name, \'\')) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(COALESCE(nice_name, \'\')) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(COALESCE(iso, \'\')) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(COALESCE(currency, \'\')) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(COALESCE(phone_code, \'\')) LIKE ?', [$like]);
                });
            }

            return $q->orderBy('name', 'asc')->limit(100)->get();
        };

        $queryCities = function () use ($citySearch, $countryId) {
            $q = City::with('country:id,name,currency')
                ->select('id', 'country_id', 'name');

            if (!empty($countryId)) {
                $q->where('country_id', $countryId);
            }

            if ($citySearch !== '') {
                $like = "%{$citySearch}%";
                $q->whereRaw('LOWER(COALESCE(name, \'\')) LIKE ?', [$like]);
            }

            return $q->orderBy('name', 'asc')->limit(50)->get();
        };

        // Fast scoped lookup path for live autocomplete search
        if ($scope === 'owners') {
            return response()->json(['status' => true, 'data' => ['owners' => $queryOwners()]]);
        }
        if ($scope === 'groups') {
            return response()->json(['status' => true, 'data' => ['business_groups' => $queryGroups()]]);
        }
        if ($scope === 'countries') {
            return response()->json(['status' => true, 'data' => ['countries' => $queryCountries()]]);
        }
        if ($scope === 'cities') {
            return response()->json(['status' => true, 'data' => ['cities' => $queryCities()]]);
        }

        $owners = $queryOwners();
        $businessGroups = $queryGroups();
        $countries = $queryCountries();
        $cities = $queryCities();

        // Roles (all created roles)
        $roles = Role::where('is_deleted', false)
            ->select('id', 'title', 'slug', 'description')
            ->orderBy('title', 'asc')
            ->get();

        // Existing schemas for schema dropdown (from PostgreSQL and existing businesses)
        $pgSchemas = TenantSchemaService::getAllSchemas();
        $businessSchemas = Business::whereNotNull('schema_name')
            ->where('schema_name', '!=', '')
            ->distinct()
            ->pluck('schema_name')
            ->toArray();
        $existingSchemas = array_values(array_unique(array_merge($pgSchemas, $businessSchemas)));

        $defaultBusinessTypes = [
            'Electronics Store',
            'Mobile & Smartphone Store',
            'Phone & Tablet Repair Shop',
            'Computer & Laptop Store',
            'Gadgets & Trade-in Store',
            'Repair Center',
            'Tech & Electronics Accessories',
            'Gaming & Console Store',
            'Home Appliances Store',
            'IT Hardware & Networking',
            'Telecom & SIM Retailer',
            'Camera & Photography Store',
            'Audio & Hi-Fi Store',
            'Watch & Wearable Tech Store',
            'Vape & E-Cigarette Shop',
            'Liquor Store',
            'Apparel Store',
            'Footwear & Sneakers Store',
            'Fashion & Boutique',
            'Jewelry & Accessories',
            'Cosmetics & Beauty Supply',
            'Flower Shop',
            'Gift & Novelty Shop',
            'Restaurant / Cafe',
            'Bakery & Confectionery',
            'Fast Food & Takeaway',
            'Grocery Store',
            'Supermarket & Convenience Store',
            'Pharmacy',
            'Health & Wellness Store',
            'Hardware & DIY Tools',
            'Home & Furniture Store',
            'Books & Stationery',
            'Sports & Fitness Store',
            'Toy & Hobby Shop',
            'Pet Shop & Supplies',
            'Auto Parts & Bike Accessories',
            'Department Store',
            'Wholesale & Distribution',
            'General Merchandise',
        ];
        $existingBusinessTypes = Business::whereNotNull('business_type')
            ->where('business_type', '!=', '')
            ->distinct()
            ->pluck('business_type')
            ->toArray();
        $businessTypes = array_values(array_unique(array_merge($defaultBusinessTypes, $existingBusinessTypes)));

        $currencies = [
            ['code' => 'GBP', 'name' => 'British Pound (£)'],
            ['code' => 'PKR', 'name' => 'Pakistani Rupee (₨)'],
            ['code' => 'USD', 'name' => 'US Dollar ($)'],
            ['code' => 'EUR', 'name' => 'Euro (€)'],
            ['code' => 'AED', 'name' => 'UAE Dirham (د.إ)'],
            ['code' => 'SAR', 'name' => 'Saudi Riyal (﷼)'],
            ['code' => 'CAD', 'name' => 'Canadian Dollar (CA$)'],
            ['code' => 'AUD', 'name' => 'Australian Dollar (A$)'],
            ['code' => 'AFN', 'name' => 'Afghan Afghani (؋)'],
            ['code' => 'ALL', 'name' => 'Albanian Lek (L)'],
            ['code' => 'AMD', 'name' => 'Armenian Dram (֏)'],
            ['code' => 'ANG', 'name' => 'Netherlands Antillean Guilder (ƒ)'],
            ['code' => 'AOA', 'name' => 'Angolan Kwanza (Kz)'],
            ['code' => 'ARS', 'name' => 'Argentine Peso ($)'],
            ['code' => 'AWG', 'name' => 'Aruban Florin (ƒ)'],
            ['code' => 'AZN', 'name' => 'Azerbaijani Manat (₼)'],
            ['code' => 'BAM', 'name' => 'Bosnia-Herzegovina Convertible Mark (KM)'],
            ['code' => 'BBD', 'name' => 'Barbadian Dollar ($)'],
            ['code' => 'BDT', 'name' => 'Bangladeshi Taka (৳)'],
            ['code' => 'BGN', 'name' => 'Bulgarian Lev (лв)'],
            ['code' => 'BHD', 'name' => 'Bahraini Dinar (.د.ب)'],
            ['code' => 'BIF', 'name' => 'Burundian Franc (FBu)'],
            ['code' => 'BMD', 'name' => 'Bermudian Dollar ($)'],
            ['code' => 'BND', 'name' => 'Brunei Dollar ($)'],
            ['code' => 'BOB', 'name' => 'Bolivian Boliviano (Bs.)'],
            ['code' => 'BRL', 'name' => 'Brazilian Real (R$)'],
            ['code' => 'BSD', 'name' => 'Bahamian Dollar ($)'],
            ['code' => 'BTN', 'name' => 'Bhutanese Ngultrum (Nu.)'],
            ['code' => 'BWP', 'name' => 'Botswanan Pula (P)'],
            ['code' => 'BYN', 'name' => 'Belarusian Ruble (Br)'],
            ['code' => 'BZD', 'name' => 'Belize Dollar ($)'],
            ['code' => 'CDF', 'name' => 'Congolese Franc (FC)'],
            ['code' => 'CHF', 'name' => 'Swiss Franc (CHF)'],
            ['code' => 'CLP', 'name' => 'Chilean Peso ($)'],
            ['code' => 'CNY', 'name' => 'Chinese Yuan (¥)'],
            ['code' => 'COP', 'name' => 'Colombian Peso ($)'],
            ['code' => 'CRC', 'name' => 'Costa Rican Colón (₡)'],
            ['code' => 'CUP', 'name' => 'Cuban Peso ($)'],
            ['code' => 'CVE', 'name' => 'Cape Verdean Escudo ($)'],
            ['code' => 'CZK', 'name' => 'Czech Koruna (Kč)'],
            ['code' => 'DJF', 'name' => 'Djiboutian Franc (Fdj)'],
            ['code' => 'DKK', 'name' => 'Danish Krone (kr)'],
            ['code' => 'DOP', 'name' => 'Dominican Peso (RD$)'],
            ['code' => 'DZD', 'name' => 'Algerian Dinar (د.ج)'],
            ['code' => 'EGP', 'name' => 'Egyptian Pound (£)'],
            ['code' => 'ERN', 'name' => 'Eritrean Nakfa (Nfk)'],
            ['code' => 'ETB', 'name' => 'Ethiopian Birr (Br)'],
            ['code' => 'FJD', 'name' => 'Fijian Dollar ($)'],
            ['code' => 'FKP', 'name' => 'Falkland Islands Pound (£)'],
            ['code' => 'GEL', 'name' => 'Georgian Lari (₾)'],
            ['code' => 'GHS', 'name' => 'Ghanaian Cedi (₵)'],
            ['code' => 'GIP', 'name' => 'Gibraltar Pound (£)'],
            ['code' => 'GMD', 'name' => 'Gambian Dalasi (D)'],
            ['code' => 'GNF', 'name' => 'Guinean Franc (FG)'],
            ['code' => 'GTQ', 'name' => 'Guatemalan Quetzal (Q)'],
            ['code' => 'GYD', 'name' => 'Guyanese Dollar ($)'],
            ['code' => 'HKD', 'name' => 'Hong Kong Dollar (HK$)'],
            ['code' => 'HNL', 'name' => 'Honduran Lempira (L)'],
            ['code' => 'HTG', 'name' => 'Haitian Gourde (G)'],
            ['code' => 'HUF', 'name' => 'Hungarian Forint (Ft)'],
            ['code' => 'IDR', 'name' => 'Indonesian Rupiah (Rp)'],
            ['code' => 'ILS', 'name' => 'Israeli New Shekel (₪)'],
            ['code' => 'INR', 'name' => 'Indian Rupee (₹)'],
            ['code' => 'IQD', 'name' => 'Iraqi Dinar (ع.د)'],
            ['code' => 'IRR', 'name' => 'Iranian Rial (﷼)'],
            ['code' => 'ISK', 'name' => 'Icelandic Króna (kr)'],
            ['code' => 'JMD', 'name' => 'Jamaican Dollar (J$)'],
            ['code' => 'JOD', 'name' => 'Jordanian Dinar (د.ا)'],
            ['code' => 'JPY', 'name' => 'Japanese Yen (¥)'],
            ['code' => 'KES', 'name' => 'Kenyan Shilling (KSh)'],
            ['code' => 'KGS', 'name' => 'Kyrgyzstani Som (с)'],
            ['code' => 'KHR', 'name' => 'Cambodian Riel (៛)'],
            ['code' => 'KMF', 'name' => 'Comorian Franc (CF)'],
            ['code' => 'KRW', 'name' => 'South Korean Won (₩)'],
            ['code' => 'KWD', 'name' => 'Kuwaiti Dinar (د.ك)'],
            ['code' => 'KYD', 'name' => 'Cayman Islands Dollar ($)'],
            ['code' => 'KZT', 'name' => 'Kazakhstani Tenge (₸)'],
            ['code' => 'LAK', 'name' => 'Laotian Kip (₭)'],
            ['code' => 'LBP', 'name' => 'Lebanese Pound (ل.ل)'],
            ['code' => 'LKR', 'name' => 'Sri Lankan Rupee (Rs)'],
            ['code' => 'LRD', 'name' => 'Liberian Dollar ($)'],
            ['code' => 'LSL', 'name' => 'Lesotho Loti (L)'],
            ['code' => 'LYD', 'name' => 'Libyan Dinar (ل.د)'],
            ['code' => 'MAD', 'name' => 'Moroccan Dirham (د.م.)'],
            ['code' => 'MDL', 'name' => 'Moldovan Leu (L)'],
            ['code' => 'MGA', 'name' => 'Malagasy Ariary (Ar)'],
            ['code' => 'MKD', 'name' => 'Macedonian Denar (ден)'],
            ['code' => 'MMK', 'name' => 'Myanmar Kyat (K)'],
            ['code' => 'MNT', 'name' => 'Mongolian Tugrik (₮)'],
            ['code' => 'MOP', 'name' => 'Macanese Pataca (MOP$)'],
            ['code' => 'MRU', 'name' => 'Mauritanian Ouguiya (UM)'],
            ['code' => 'MUR', 'name' => 'Mauritian Rupee (₨)'],
            ['code' => 'MVR', 'name' => 'Maldivian Rufiyaa (Rf)'],
            ['code' => 'MWK', 'name' => 'Malawian Kwacha (MK)'],
            ['code' => 'MXN', 'name' => 'Mexican Peso ($)'],
            ['code' => 'MYR', 'name' => 'Malaysian Ringgit (RM)'],
            ['code' => 'MZN', 'name' => 'Mozambican Metical (MT)'],
            ['code' => 'NAD', 'name' => 'Namibian Dollar ($)'],
            ['code' => 'NGN', 'name' => 'Nigerian Naira (₦)'],
            ['code' => 'NIO', 'name' => 'Nicaraguan Córdoba (C$)'],
            ['code' => 'NOK', 'name' => 'Norwegian Krone (kr)'],
            ['code' => 'NPR', 'name' => 'Nepalese Rupee (₨)'],
            ['code' => 'NZD', 'name' => 'New Zealand Dollar (NZ$)'],
            ['code' => 'OMR', 'name' => 'Omani Rial (ر.ع.)'],
            ['code' => 'PAB', 'name' => 'Panamanian Balboa (B/.)'],
            ['code' => 'PEN', 'name' => 'Peruvian Sol (S/.)'],
            ['code' => 'PGK', 'name' => 'Papua New Guinean Kina (K)'],
            ['code' => 'PHP', 'name' => 'Philippine Peso (₱)'],
            ['code' => 'PLN', 'name' => 'Polish Zloty (zł)'],
            ['code' => 'PYG', 'name' => 'Paraguayan Guarani (₲)'],
            ['code' => 'QAR', 'name' => 'Qatari Riyal (ر.ق)'],
            ['code' => 'RON', 'name' => 'Romanian Leu (lei)'],
            ['code' => 'RSD', 'name' => 'Serbian Dinar (дин.)'],
            ['code' => 'RUB', 'name' => 'Russian Ruble (₽)'],
            ['code' => 'RWF', 'name' => 'Rwandan Franc (FRw)'],
            ['code' => 'SBD', 'name' => 'Solomon Islands Dollar ($)'],
            ['code' => 'SCR', 'name' => 'Seychellois Rupee (₨)'],
            ['code' => 'SDG', 'name' => 'Sudanese Pound (£)'],
            ['code' => 'SEK', 'name' => 'Swedish Krona (kr)'],
            ['code' => 'SGD', 'name' => 'Singapore Dollar (S$)'],
            ['code' => 'SHP', 'name' => 'Saint Helena Pound (£)'],
            ['code' => 'SLE', 'name' => 'Sierra Leonean Leone (Le)'],
            ['code' => 'SOS', 'name' => 'Somali Shilling (Sh)'],
            ['code' => 'SRD', 'name' => 'Surinamese Dollar ($)'],
            ['code' => 'SSP', 'name' => 'South Sudanese Pound (£)'],
            ['code' => 'STN', 'name' => 'São Tomé and Príncipe Dobra (Db)'],
            ['code' => 'SYP', 'name' => 'Syrian Pound (£)'],
            ['code' => 'SZL', 'name' => 'Swazi Lilangeni (L)'],
            ['code' => 'THB', 'name' => 'Thai Baht (฿)'],
            ['code' => 'TJS', 'name' => 'Tajikistani Somoni (ЅМ)'],
            ['code' => 'TMT', 'name' => 'Turkmenistani Manat (m)'],
            ['code' => 'TND', 'name' => 'Tunisian Dinar (د.ت)'],
            ['code' => 'TOP', 'name' => 'Tongan Paʻanga (T$)'],
            ['code' => 'TRY', 'name' => 'Turkish Lira (₺)'],
            ['code' => 'TTD', 'name' => 'Trinidad & Tobago Dollar (TT$)'],
            ['code' => 'TWD', 'name' => 'New Taiwan Dollar (NT$)'],
            ['code' => 'TZS', 'name' => 'Tanzanian Shilling (TSh)'],
            ['code' => 'UAH', 'name' => 'Ukrainian Hryvnia (₴)'],
            ['code' => 'UGX', 'name' => 'Ugandan Shilling (USh)'],
            ['code' => 'UYU', 'name' => 'Uruguayan Peso ($U)'],
            ['code' => 'UZS', 'name' => 'Uzbekistan Som (сўм)'],
            ['code' => 'VES', 'name' => 'Venezuelan Bolívar (Bs.S)'],
            ['code' => 'VND', 'name' => 'Vietnamese Dong (₫)'],
            ['code' => 'VUV', 'name' => 'Vanuatu Vatu (VT)'],
            ['code' => 'WST', 'name' => 'Samoan Tala (T)'],
            ['code' => 'XAF', 'name' => 'Central African CFA Franc (FCFA)'],
            ['code' => 'XCD', 'name' => 'East Caribbean Dollar ($)'],
            ['code' => 'XOF', 'name' => 'West African CFA Franc (CFA)'],
            ['code' => 'XPF', 'name' => 'CFP Franc (₣)'],
            ['code' => 'YER', 'name' => 'Yemeni Rial (﷼)'],
            ['code' => 'ZAR', 'name' => 'South African Rand (R)'],
            ['code' => 'ZMW', 'name' => 'Zambian Kwacha (ZK)'],
            ['code' => 'ZWL', 'name' => 'Zimbabwean Dollar ($)'],
        ];

        return response()->json([
            'status' => true,
            'data' => [
                'owners' => $owners,
                'roles' => $roles,
                'countries' => $countries,
                'cities' => $cities,
                'existing_schemas' => $existingSchemas,
                'business_types' => $businessTypes,
                'currencies' => $currencies,
                'business_groups' => $businessGroups,
            ],
        ]);
    }

    /**
     * Store a newly created business.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'business_type' => 'required|string|max:255',
            'branch_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'owner_id' => 'required|exists:users,id',
            'role_id' => 'required|string|exists:roles,id',
            'country_id' => 'required|integer|exists:countries,id',
            'city_id' => 'required|integer|exists:cities,id',
            'services' => $this->coreServiceRule(),
            'package_level' => 'required|string|in:Basic,Standard,Premium',
            'schema_type' => 'required|string|in:new,existing',
            'schema_name' => 'nullable|string|max:100',
            'mobile_no' => 'nullable|string|max:50',
            'whatsapp_no' => 'nullable|string|max:50',
            'is_system_website' => 'boolean',
            'is_personal_website' => 'boolean',
            'website_url' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:500',
            'city_name' => 'nullable|string|max:100',
            'state_region' => 'nullable|string|max:100',
            'post_code' => 'nullable|string|max:50',
            'currency' => 'nullable|string|max:10',
            'latitude' => 'nullable|string|max:50',
            'longitude' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'business_group_id' => 'nullable|integer',
        ], [
            'services.required' => 'Please select at least one of Sale, Trade-in, or Repair.',
            'services.min' => 'Please select at least one of Sale, Trade-in, or Repair.',
            'role_id.required' => 'Please select a role for the business.',
            'country_id.required' => 'Please select a country.',
            'city_id.required' => 'Please select a city.',
        ]);

        // Auto resolve role title
        if (!empty($validated['role_id'])) {
            $role = Role::find($validated['role_id']);
            $validated['role_title'] = $role ? $role->title : null;
        }

        // Auto resolve country name
        if (!empty($validated['country_id'])) {
            $country = Country::find($validated['country_id']);
            $validated['country_name'] = $country ? $country->name : null;
        }

        // Auto resolve city name
        if (!empty($validated['city_id'])) {
            $city = City::find($validated['city_id']);
            $validated['city_name'] = $city ? $city->name : ($validated['city_name'] ?? null);
        }

        // Sanitize and provision PostgreSQL tenant schema
        $rawSchema = !empty($validated['schema_name']) ? $validated['schema_name'] : 'public';
        $schemaName = TenantSchemaService::sanitizeSchemaName($rawSchema);
        $validated['schema_name'] = $schemaName;

        // If the user selected "new" schema, verify it does not already exist
        if ($validated['schema_type'] === 'new') {
            $businessExistsWithSchema = Business::where('schema_name', $schemaName)->exists();
            $pgSchemaExists = TenantSchemaService::schemaExists($schemaName);

            if ($businessExistsWithSchema || $pgSchemaExists) {
                return response()->json([
                    'status' => false,
                    'message' => "The schema \"{$schemaName}\" already exists. Please choose a different name or select 'Use an existing schema'.",
                    'errors' => [
                        'schema_name' => ["The schema \"{$schemaName}\" already exists."],
                    ],
                ], 422);
            }
        }

        $provisionResult = TenantSchemaService::provisionTenantSchema($schemaName);

        $business = Business::create($validated);

        // When a business is created against business owner, ensure user_type becomes 2 (Business Owner / Vendor)
        if (!empty($validated['owner_id'])) {
            $ownerUser = User::find($validated['owner_id']);
            if ($ownerUser) {
                $ownerUser->user_type = 2;
                if (empty($ownerUser->business_name)) {
                    $ownerUser->business_name = $business->name . ($business->branch_name ? " · {$business->branch_name} owner" : " owner");
                }
                $ownerUser->save();
            }
        }

        return response()->json([
            'status' => true,
            'message' => 'Business and database schema created successfully',
            'schema_provisioned' => $provisionResult,
            'data' => $business->load(['owner', 'role', 'country', 'city']),
        ], 201);
    }

    /**
     * Display the specified business.
     */
    public function show($id)
    {
        $business = Business::withTrashed()->with(['owner', 'role', 'country', 'city', 'group'])->find($id);

        if (!$business) {
            return response()->json([
                'status' => false,
                'message' => 'Business not found',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $business,
        ]);
    }

    /**
     * Update the specified business.
     */
    public function update(Request $request, $id)
    {
        $business = Business::withTrashed()->find($id);

        if (!$business) {
            return response()->json([
                'status' => false,
                'message' => 'Business not found',
            ], 404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'business_type' => 'required|string|max:255',
            'branch_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'owner_id' => 'nullable|exists:users,id',
            'role_id' => 'required|string|exists:roles,id',
            'country_id' => 'required|integer|exists:countries,id',
            'city_id' => 'required|integer|exists:cities,id',
            'services' => $this->coreServiceRule(),
            'package_level' => 'required|string|in:Basic,Standard,Premium',
            'schema_type' => 'nullable|string',
            'schema_name' => 'nullable|string|max:100',
            'mobile_no' => 'nullable|string|max:50',
            'whatsapp_no' => 'nullable|string|max:50',
            'is_system_website' => 'boolean',
            'is_personal_website' => 'boolean',
            'website_url' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:500',
            'city_name' => 'nullable|string|max:100',
            'state_region' => 'nullable|string|max:100',
            'post_code' => 'nullable|string|max:50',
            'currency' => 'nullable|string|max:10',
            'latitude' => 'nullable|string|max:50',
            'longitude' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'business_group_id' => 'nullable|integer',
        ], [
            'services.required' => 'Please select at least one of Sale, Trade-in, or Repair.',
            'services.min' => 'Please select at least one of Sale, Trade-in, or Repair.',
            'role_id.required' => 'Please select a role for the business.',
            'country_id.required' => 'Please select a country.',
            'city_id.required' => 'Please select a city.',
        ]);

        // Schema and owner are immutable after creation
        unset($validated['schema_name'], $validated['schema_type']);

        if (isset($validated['role_id'])) {
            $role = Role::find($validated['role_id']);
            $validated['role_title'] = $role ? $role->title : null;
        }

        if (isset($validated['country_id'])) {
            $country = Country::find($validated['country_id']);
            $validated['country_name'] = $country ? $country->name : null;
        }

        if (isset($validated['city_id'])) {
            $city = City::find($validated['city_id']);
            $validated['city_name'] = $city ? $city->name : ($validated['city_name'] ?? null);
        }

        $business->update($validated);

        // Ensure user_type is 2 (Business Owner / Vendor) for the assigned owner
        if (!empty($validated['owner_id'])) {
            $ownerUser = User::find($validated['owner_id']);
            if ($ownerUser && $ownerUser->user_type !== 2) {
                $ownerUser->user_type = 2;
                if (empty($ownerUser->business_name)) {
                    $ownerUser->business_name = $business->name . ($business->branch_name ? " · {$business->branch_name} owner" : " owner");
                }
                $ownerUser->save();
            }
        }

        return response()->json([
            'status' => true,
            'message' => 'Business updated successfully',
            'data' => $business->load(['owner', 'role', 'country', 'city']),
        ]);
    }

    /**
     * Toggle active/blocked status for a business.
     */
    public function toggleBlock($id)
    {
        $business = Business::find($id);

        if (!$business) {
            return response()->json([
                'status' => false,
                'message' => 'Business not found',
            ], 404);
        }

        $newStatus = ($business->status === 'active') ? 'blocked' : 'active';
        $business->status = $newStatus;
        $business->save();

        return response()->json([
            'status' => true,
            'message' => "Business status changed to {$newStatus}",
            'data' => [
                'id' => $business->id,
                'status' => $newStatus,
            ],
        ]);
    }

    /**
     * Remove or restore the specified business.
     */
    public function destroy($id)
    {
        $business = Business::withTrashed()->find($id);

        if (!$business) {
            return response()->json([
                'status' => false,
                'message' => 'Business not found',
            ], 404);
        }

        if ($business->trashed()) {
            // Restore if already in trash
            $business->restore();
            return response()->json([
                'status' => true,
                'message' => 'Business restored successfully',
            ]);
        }

        $business->delete(); // Soft delete

        return response()->json([
            'status' => true,
            'message' => 'Business moved to deleted',
        ]);
    }

    /**
     * Core service identifiers where at least one is required per business.
     */
    private const REQUIRED_CORE_SERVICES = ['sale', 'trade_in', 'repair'];

    /**
     * Shared validation rule requiring at least one core service (Sale, Trade-in, or Repair).
     */
    private function coreServiceRule(): array
    {
        return [
            'required',
            'array',
            'min:1',
            function (string $attribute, mixed $value, \Closure $fail): void {
                if (!is_array($value) || empty(array_intersect(self::REQUIRED_CORE_SERVICES, $value))) {
                    $fail('Please select at least one of Sale, Trade-in, or Repair.');
                }
            },
        ];
    }

    /**
     * Return live schema provisioning progress for a tenant schema.
     */
    public function schemaProgress(string $schemaName)
    {
        $clean = TenantSchemaService::sanitizeSchemaName($schemaName);
        $progress = Cache::get("schema_progress_{$clean}", [
            'applied' => 0,
            'total' => 22,
            'current' => 'initializing',
            'done' => false,
        ]);

        return response()->json([
            'status' => true,
            'data' => $progress,
        ]);
    }
}
