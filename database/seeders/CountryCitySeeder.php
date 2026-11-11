<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Country;
use App\Models\City;

class CountryCitySeeder extends Seeder
{
    public function run(): void
    {
        $countries = [
            [
                'name' => 'Afghanistan',
                'nice_name' => 'Afghanistan',
                'iso3' => 'AFG',
                'iso' => 'AF',
                'phone_code' => '93',
                'num_code' => '4',
                'currency' => 'AFN',
                'time_zone' => 'Asia/Kabul',
                'status' => true,
            ],
            [
                'name' => 'Albania',
                'nice_name' => 'Albania',
                'iso3' => 'ALB',
                'iso' => 'AL',
                'phone_code' => '355',
                'num_code' => '8',
                'currency' => 'ALL',
                'time_zone' => 'Europe/Tirane',
                'status' => true,
            ],
            [
                'name' => 'Algeria',
                'nice_name' => 'Algeria',
                'iso3' => 'DZA',
                'iso' => 'DZ',
                'phone_code' => '213',
                'num_code' => '12',
                'currency' => 'DZD',
                'time_zone' => 'Africa/Algiers',
                'status' => true,
            ],
            [
                'name' => 'American Samoa',
                'nice_name' => 'American Samoa',
                'iso3' => 'ASM',
                'iso' => 'AS',
                'phone_code' => '1684',
                'num_code' => '16',
                'currency' => 'USD',
                'time_zone' => 'Pacific/Pago_Pago',
                'status' => true,
            ],
            [
                'name' => 'Andorra',
                'nice_name' => 'Andorra',
                'iso3' => 'AND',
                'iso' => 'AD',
                'phone_code' => '376',
                'num_code' => '20',
                'currency' => 'EUR',
                'time_zone' => 'Europe/Andorra',
                'status' => true,
            ],
            [
                'name' => 'Angola',
                'nice_name' => 'Angola',
                'iso3' => 'AGO',
                'iso' => 'AO',
                'phone_code' => '244',
                'num_code' => '24',
                'currency' => 'AOA',
                'time_zone' => 'Africa/Luanda',
                'status' => true,
            ],
            [
                'name' => 'Anguilla',
                'nice_name' => 'Anguilla',
                'iso3' => 'AIA',
                'iso' => 'AI',
                'phone_code' => '1264',
                'num_code' => '660',
                'currency' => 'XCD',
                'time_zone' => 'America/Anguilla',
                'status' => true,
            ],
            [
                'name' => 'Antigua and Barbuda',
                'nice_name' => 'Antigua and Barbuda',
                'iso3' => 'ATG',
                'iso' => 'AG',
                'phone_code' => '1268',
                'num_code' => '28',
                'currency' => 'XCD',
                'time_zone' => 'America/Antigua',
                'status' => true,
            ],
            [
                'name' => 'Argentina',
                'nice_name' => 'Argentina',
                'iso3' => 'ARG',
                'iso' => 'AR',
                'phone_code' => '54',
                'num_code' => '32',
                'currency' => 'ARS',
                'time_zone' => 'America/Argentina/Buenos_Aires',
                'status' => true,
            ],
            [
                'name' => 'Armenia',
                'nice_name' => 'Armenia',
                'iso3' => 'ARM',
                'iso' => 'AM',
                'phone_code' => '374',
                'num_code' => '51',
                'currency' => 'AMD',
                'time_zone' => 'Asia/Yerevan',
                'status' => true,
            ],
            [
                'name' => 'Aruba',
                'nice_name' => 'Aruba',
                'iso3' => 'ABW',
                'iso' => 'AW',
                'phone_code' => '297',
                'num_code' => '533',
                'currency' => 'AWG',
                'time_zone' => 'America/Aruba',
                'status' => true,
            ],
            [
                'name' => 'Australia',
                'nice_name' => 'Australia',
                'iso3' => 'AUS',
                'iso' => 'AU',
                'phone_code' => '61',
                'num_code' => '36',
                'currency' => 'AUD',
                'time_zone' => 'Australia/Sydney',
                'status' => true,
            ],
            [
                'name' => 'Canada',
                'nice_name' => 'Canada',
                'iso3' => 'CAN',
                'iso' => 'CA',
                'phone_code' => '1',
                'num_code' => '124',
                'currency' => 'CAD',
                'time_zone' => 'America/Toronto',
                'status' => true,
            ],
            [
                'name' => 'Germany',
                'nice_name' => 'Germany',
                'iso3' => 'DEU',
                'iso' => 'DE',
                'phone_code' => '49',
                'num_code' => '276',
                'currency' => 'EUR',
                'time_zone' => 'Europe/Berlin',
                'status' => true,
            ],
            [
                'name' => 'Pakistan',
                'nice_name' => 'Pakistan',
                'iso3' => 'PAK',
                'iso' => 'PK',
                'phone_code' => '92',
                'num_code' => '586',
                'currency' => 'PKR',
                'time_zone' => 'Asia/Karachi',
                'status' => true,
            ],
            [
                'name' => 'United Kingdom',
                'nice_name' => 'United Kingdom',
                'iso3' => 'GBR',
                'iso' => 'GB',
                'phone_code' => '44',
                'num_code' => '826',
                'currency' => 'GBP',
                'time_zone' => 'Europe/London',
                'status' => true,
            ],
            [
                'name' => 'United States',
                'nice_name' => 'United States',
                'iso3' => 'USA',
                'iso' => 'US',
                'phone_code' => '1',
                'num_code' => '840',
                'currency' => 'USD',
                'time_zone' => 'America/New_York',
                'status' => true,
            ],
        ];

        foreach ($countries as $cData) {
            $country = Country::updateOrCreate(['name' => $cData['name']], $cData);

            if ($country->name === 'United Kingdom') {
                $ukCities = ['Bedford', 'Birmingham', 'Chelmsford', 'Crawley', 'London', 'Manchester', 'Leeds', 'Liverpool'];
                foreach ($ukCities as $cityName) {
                    City::updateOrCreate(
                        ['country_id' => $country->id, 'name' => $cityName],
                        ['status' => true]
                    );
                }
            } elseif ($country->name === 'United States') {
                $usCities = ['New York', 'Los Angeles', 'Chicago', 'Houston', 'Phoenix'];
                foreach ($usCities as $cityName) {
                    City::updateOrCreate(
                        ['country_id' => $country->id, 'name' => $cityName],
                        ['status' => true]
                    );
                }
            } elseif ($country->name === 'Pakistan') {
                $pkCities = ['Islamabad', 'Lahore', 'Karachi', 'Rawalpindi'];
                foreach ($pkCities as $cityName) {
                    City::updateOrCreate(
                        ['country_id' => $country->id, 'name' => $cityName],
                        ['status' => true]
                    );
                }
            }
        }
    }
}
