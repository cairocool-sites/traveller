<?php

namespace Database\Seeders;

use App\Enums\FacilityCategory;
use App\Models\City;
use App\Models\Country;
use App\Models\Currency;
use App\Models\Facility;
use Illuminate\Database\Seeder;

class CoreReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedCountriesAndCities();
        $this->seedCurrencies();
        $this->seedFacilities();
    }

    private function seedCountriesAndCities(): void
    {
        $countries = [
            ['iso2' => 'EG', 'iso3' => 'EGY', 'numeric_code' => '818', 'phone_code' => '+20', 'name_en' => 'Egypt', 'name_ar' => 'مصر', 'nationality_en' => 'Egyptian', 'nationality_ar' => 'مصري', 'currency_code' => 'EGP', 'sort_order' => 10],
            ['iso2' => 'SA', 'iso3' => 'SAU', 'numeric_code' => '682', 'phone_code' => '+966', 'name_en' => 'Saudi Arabia', 'name_ar' => 'السعودية', 'nationality_en' => 'Saudi', 'nationality_ar' => 'سعودي', 'currency_code' => 'SAR', 'sort_order' => 20],
            ['iso2' => 'AE', 'iso3' => 'ARE', 'numeric_code' => '784', 'phone_code' => '+971', 'name_en' => 'United Arab Emirates', 'name_ar' => 'الإمارات العربية المتحدة', 'nationality_en' => 'Emirati', 'nationality_ar' => 'إماراتي', 'currency_code' => 'AED', 'sort_order' => 30],
            ['iso2' => 'TR', 'iso3' => 'TUR', 'numeric_code' => '792', 'phone_code' => '+90', 'name_en' => 'Turkey', 'name_ar' => 'تركيا', 'nationality_en' => 'Turkish', 'nationality_ar' => 'تركي', 'currency_code' => 'TRY', 'sort_order' => 40],
            ['iso2' => 'GB', 'iso3' => 'GBR', 'numeric_code' => '826', 'phone_code' => '+44', 'name_en' => 'United Kingdom', 'name_ar' => 'المملكة المتحدة', 'nationality_en' => 'British', 'nationality_ar' => 'بريطاني', 'currency_code' => 'GBP', 'sort_order' => 50],
            ['iso2' => 'US', 'iso3' => 'USA', 'numeric_code' => '840', 'phone_code' => '+1', 'name_en' => 'United States', 'name_ar' => 'الولايات المتحدة', 'nationality_en' => 'American', 'nationality_ar' => 'أمريكي', 'currency_code' => 'USD', 'sort_order' => 60],
        ];

        foreach ($countries as $countryData) {
            Country::query()->firstOrCreate(
                ['iso2' => $countryData['iso2']],
                [...$countryData, 'is_active' => true],
            );
        }

        $cities = [
            'EG' => ['Cairo', 'Giza', 'Alexandria', 'Hurghada', 'Sharm El Sheikh', 'Luxor', 'Aswan'],
            'SA' => ['Makkah', 'Madinah', 'Riyadh', 'Jeddah'],
            'AE' => ['Dubai', 'Abu Dhabi', 'Sharjah', 'Ras Al Khaimah'],
            'TR' => ['Istanbul', 'Antalya'],
        ];

        $arabicNames = [
            'Cairo' => 'القاهرة',
            'Giza' => 'الجيزة',
            'Alexandria' => 'الإسكندرية',
            'Hurghada' => 'الغردقة',
            'Sharm El Sheikh' => 'شرم الشيخ',
            'Luxor' => 'الأقصر',
            'Aswan' => 'أسوان',
            'Makkah' => 'مكة',
            'Madinah' => 'المدينة المنورة',
            'Riyadh' => 'الرياض',
            'Jeddah' => 'جدة',
            'Dubai' => 'دبي',
            'Abu Dhabi' => 'أبوظبي',
            'Sharjah' => 'الشارقة',
            'Ras Al Khaimah' => 'رأس الخيمة',
            'Istanbul' => 'إسطنبول',
            'Antalya' => 'أنطاليا',
        ];

        foreach ($cities as $countryIso2 => $cityNames) {
            $country = Country::query()->where('iso2', $countryIso2)->firstOrFail();

            foreach ($cityNames as $index => $cityName) {
                City::query()->firstOrCreate(
                    ['country_id' => $country->id, 'name_en' => $cityName],
                    [
                        'name_ar' => $arabicNames[$cityName],
                        'timezone' => match ($countryIso2) {
                            'EG' => 'Africa/Cairo',
                            'SA' => 'Asia/Riyadh',
                            'AE' => 'Asia/Dubai',
                            'TR' => 'Europe/Istanbul',
                            default => null,
                        },
                        'is_active' => true,
                        'is_featured' => in_array($cityName, ['Cairo', 'Dubai', 'Makkah', 'Istanbul'], true),
                        'sort_order' => ($index + 1) * 10,
                    ],
                );
            }
        }
    }

    private function seedCurrencies(): void
    {
        $currencies = [
            ['code' => 'USD', 'numeric_code' => '840', 'name_en' => 'US Dollar', 'name_ar' => 'دولار أمريكي', 'symbol' => '$', 'decimal_places' => 2, 'is_base' => true, 'sort_order' => 10],
            ['code' => 'EGP', 'numeric_code' => '818', 'name_en' => 'Egyptian Pound', 'name_ar' => 'جنيه مصري', 'symbol' => 'E£', 'decimal_places' => 2, 'is_base' => false, 'sort_order' => 20],
            ['code' => 'EUR', 'numeric_code' => '978', 'name_en' => 'Euro', 'name_ar' => 'يورو', 'symbol' => '€', 'decimal_places' => 2, 'is_base' => false, 'sort_order' => 30],
            ['code' => 'SAR', 'numeric_code' => '682', 'name_en' => 'Saudi Riyal', 'name_ar' => 'ريال سعودي', 'symbol' => 'SAR', 'decimal_places' => 2, 'is_base' => false, 'sort_order' => 40],
            ['code' => 'AED', 'numeric_code' => '784', 'name_en' => 'UAE Dirham', 'name_ar' => 'درهم إماراتي', 'symbol' => 'AED', 'decimal_places' => 2, 'is_base' => false, 'sort_order' => 50],
            ['code' => 'GBP', 'numeric_code' => '826', 'name_en' => 'British Pound', 'name_ar' => 'جنيه إسترليني', 'symbol' => '£', 'decimal_places' => 2, 'is_base' => false, 'sort_order' => 60],
        ];

        foreach ($currencies as $currencyData) {
            Currency::query()->updateOrCreate(
                ['code' => $currencyData['code']],
                [...$currencyData, 'is_active' => true],
            );
        }

        if (! Currency::query()->where('is_base', true)->where('is_active', true)->exists()) {
            Currency::query()->where('code', 'USD')->firstOrFail()->forceFill([
                'is_active' => true,
                'is_base' => true,
            ])->save();
        }
    }

    private function seedFacilities(): void
    {
        $facilities = [
            ['code' => 'wifi', 'category' => FacilityCategory::General, 'en' => 'Wi-Fi', 'ar' => 'واي فاي'],
            ['code' => 'parking', 'category' => FacilityCategory::Transport, 'en' => 'Parking', 'ar' => 'موقف سيارات'],
            ['code' => 'swimming_pool', 'category' => FacilityCategory::Wellness, 'en' => 'Swimming pool', 'ar' => 'حمام سباحة'],
            ['code' => 'restaurant', 'category' => FacilityCategory::Food, 'en' => 'Restaurant', 'ar' => 'مطعم'],
            ['code' => 'breakfast', 'category' => FacilityCategory::Food, 'en' => 'Breakfast', 'ar' => 'إفطار'],
            ['code' => 'airport_transfer', 'category' => FacilityCategory::Transport, 'en' => 'Airport transfer', 'ar' => 'انتقال من وإلى المطار'],
            ['code' => 'air_conditioning', 'category' => FacilityCategory::Room, 'en' => 'Air conditioning', 'ar' => 'تكييف'],
            ['code' => 'family_rooms', 'category' => FacilityCategory::Family, 'en' => 'Family rooms', 'ar' => 'غرف عائلية'],
            ['code' => 'fitness_center', 'category' => FacilityCategory::Wellness, 'en' => 'Fitness center', 'ar' => 'مركز لياقة'],
            ['code' => 'spa', 'category' => FacilityCategory::Wellness, 'en' => 'Spa', 'ar' => 'سبا'],
            ['code' => 'accessible_rooms', 'category' => FacilityCategory::Accessibility, 'en' => 'Accessible rooms', 'ar' => 'غرف مهيأة لذوي الاحتياجات'],
            ['code' => 'business_center', 'category' => FacilityCategory::Business, 'en' => 'Business center', 'ar' => 'مركز أعمال'],
        ];

        foreach ($facilities as $index => $facilityData) {
            $facility = Facility::query()->firstOrCreate(
                ['code' => $facilityData['code']],
                [
                    'category' => $facilityData['category'],
                    'is_active' => true,
                    'sort_order' => ($index + 1) * 10,
                ],
            );

            foreach (['en' => $facilityData['en'], 'ar' => $facilityData['ar']] as $locale => $name) {
                $facility->translations()->firstOrCreate(
                    ['locale' => $locale],
                    ['name' => $name],
                );
            }
        }
    }
}
