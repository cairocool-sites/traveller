<?php

namespace Database\Seeders;

use App\Models\ManualPaymentMethod;
use Illuminate\Database\Seeder;

class ManualPaymentMethodSeeder extends Seeder
{
    public function run(): void
    {
        $methods = [
            ['code' => 'bank_transfer', 'name_en' => 'Bank transfer', 'name_ar' => 'تحويل بنكي', 'requires_reference' => true, 'supports_attachment' => true, 'sort_order' => 10],
            ['code' => 'cash_at_office', 'name_en' => 'Cash at office', 'name_ar' => 'دفع نقدي بالمكتب', 'requires_reference' => false, 'supports_attachment' => false, 'sort_order' => 20],
            ['code' => 'mobile_wallet', 'name_en' => 'Mobile wallet', 'name_ar' => 'محفظة موبايل', 'requires_reference' => true, 'supports_attachment' => true, 'sort_order' => 30],
            ['code' => 'manual_confirmation', 'name_en' => 'Manual confirmation', 'name_ar' => 'تأكيد يدوي', 'requires_reference' => false, 'supports_attachment' => false, 'sort_order' => 40],
        ];

        foreach ($methods as $method) {
            ManualPaymentMethod::query()->updateOrCreate(
                ['code' => $method['code']],
                array_merge($method, [
                    'instructions_en' => 'Use the safe local placeholder instructions shown by Cairo Cool Travel. No real account details are configured.',
                    'instructions_ar' => 'استخدم تعليمات الاختبار الآمنة المعروضة من Cairo Cool Travel. لا توجد بيانات حساب حقيقية.',
                    'account_name' => 'Cairo Cool Travel Placeholder',
                    'account_reference' => 'PLACEHOLDER-ONLY',
                    'is_active' => true,
                ]),
            );
        }
    }
}
