<?php

namespace App\Services;

use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\FinancialAuditLog;
use App\Models\FinancialCategory;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\User;
use App\Models\Vendor;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FinancialLedgerService
{
    /**
     * Standard default Republic of Armenia Chart of Accounts (ՀՀ Հաշվային Պլան - F&B / Restaurant).
     *
     * @var array<int, array{code: string, name: string, type: string, subtype: string, desc: string}>
     */
    public const DEFAULT_ACCOUNTS = [
        // ԴԱՍ 1. ՈՉ ԸՆԹԱՑԻԿ ԱԿՏԻՎՆԵՐ (Non-Current Assets)
        ['code' => '1114', 'name' => 'Խոհանոցային և ռեստորանային սարքավորումներ (Kitchen Machinery & Equipment)', 'type' => 'asset', 'subtype' => 'fixed_asset', 'desc' => 'Խոհանոցի արտադրական մեքենաներ, վառարաններ, սառնարաններ և սարքավորումներ։'],
        ['code' => '1116', 'name' => 'Արտադրական և տնտեսական գույք (Restaurant Furniture & Fixtures)', 'type' => 'asset', 'subtype' => 'fixed_asset', 'desc' => 'Դահլիճի և խոհանոցի կահույք, սեղաններ, աթոռներ, բարի կահավորանք։'],
        ['code' => '1121', 'name' => 'Հիմնական միջոցների մաշվածություն (Accumulated Depreciation - Fixed Assets)', 'type' => 'asset', 'subtype' => 'contra_asset', 'desc' => 'Շահագործվող հիմնական միջոցների կուտակված մաշվածություն (հակակշռային հաշիվ)։'],
        ['code' => '1311', 'name' => 'Ոչ նյութական ակտիվներ / Ծրագրային ապահովում (Software & Licenses)', 'type' => 'asset', 'subtype' => 'intangible_asset', 'desc' => 'POS ավտոմատացման համակարգի լիցենզիաներ, ծրագրային ապահովում։'],

        // ԴԱՍ 2. ԸՆԹԱՑԻԿ ԱԿՏԻՎՆԵՐ (Current Assets)
        ['code' => '2111', 'name' => 'Հումք և նյութեր (Kitchen Raw Materials & Food Stock)', 'type' => 'asset', 'subtype' => 'inventory', 'desc' => 'Սննդամթերքի, բաղադրիչների, համեմունքների և խոհանոցային հումքի պաշարներ։'],
        ['code' => '2114', 'name' => 'Տարա և տարանյութեր (Takeaway Packaging & Disposables)', 'type' => 'asset', 'subtype' => 'inventory', 'desc' => 'Առաքման տուփեր, բաժակներ, փաթեթավորման պարագաներ։'],
        ['code' => '2152', 'name' => 'Սեփական արտադրության կիսապատրաստուկներ (In-house Food Prep)', 'type' => 'asset', 'subtype' => 'inventory', 'desc' => 'Խոհանոցում նախապես պատրաստված սոուսներ, մարինացված միս, կիսապատրաստուկներ։'],
        ['code' => '2161', 'name' => 'Ապրանքներ պահեստում (Bar Beverages & Resale Goods)', 'type' => 'asset', 'subtype' => 'inventory', 'desc' => 'Բարի ալկոհոլային և ոչ ալկոհոլային խմիչքներ, ծխախոտ և վերավաճառքի ապրանքներ։'],
        ['code' => '2211', 'name' => 'Դեբիտորական պարտքեր արտադրանքի, ապրանքների վաճառքների գծով (Accounts Receivable)', 'type' => 'asset', 'subtype' => 'accounts_receivable', 'desc' => 'Հաճախորդների չվճարված հաշիվներ (tabs), կորպորատիվ միջոցառումների գծով պահանջներ։'],
        ['code' => '2281', 'name' => 'Դեբիտորական պարտքեր առհաշիվ տրված գումարների գծով (Accountable Advances)', 'type' => 'asset', 'subtype' => 'receivables', 'desc' => 'Աշխատակիցներին շուկայից գնումների կամ աշխատավարձի գծով տրված առհաշիվ գումարներ։'],
        ['code' => '2511', 'name' => 'Ազգային արժույթի դրամարկղ (Cash in POS Registers / Till)', 'type' => 'asset', 'subtype' => 'cash', 'desc' => 'Ֆիզիկական կանխիկ դրամական միջոցներ POS դրամարկղային արկղերում։'],
        ['code' => '2512', 'name' => 'Գլխավոր դրամարկղ / Պահարան (Cash in Safe / Vault)', 'type' => 'asset', 'subtype' => 'cash', 'desc' => 'Կանխիկ միջոցներ ռեստորանի կենտրոնական չհրկիզվող պահարանում (սեյֆ)։'],
        ['code' => '2521', 'name' => 'Հաշվարկային հաշիվ (Operating Bank Account - AMD)', 'type' => 'asset', 'subtype' => 'bank', 'desc' => 'Առևտրային բանկում կազմակերպության դրամային հիմնական գործառնական հաշիվ։'],
        ['code' => '2531', 'name' => 'Արտարժութային հաշիվ (Foreign Currency Bank Accounts)', 'type' => 'asset', 'subtype' => 'bank', 'desc' => 'Արտարժույթով (USD, EUR, RUB) բանկային հաշիվներ։'],
        ['code' => '2551', 'name' => 'Դրամական միջոցներ ճանապարհին - Էքվայրինգ (Card Acquirer Clearing)', 'type' => 'asset', 'subtype' => 'clearing', 'desc' => 'POS տերմինալներով քարտային վճարումներ բանկային փոխանցման սպասման փուլում։'],
        ['code' => '2552', 'name' => 'Դրամական միջոցներ ճանապարհին - Օնլայն դարպասներ (Online Payment Gateways)', 'type' => 'asset', 'subtype' => 'clearing', 'desc' => 'Իդրամ, Թելսել, ԻնեկոՓեյ, Սթրայփ օնլայն վճարային հաշվանցումներ։'],

        // ԴԱՍ 3. ՍԵՓԱԿԱՆ ԿԱՊԻՏԱԼ (Equity)
        ['code' => '3111', 'name' => 'Կանոնադրական (բաժնեհավաք) կապիտալ (Owner Capital & Equity)', 'type' => 'equity', 'subtype' => 'equity', 'desc' => 'Հիմնադիրների ներդրած կանոնադրական կապիտալ և սեփական միջոցներ։'],
        ['code' => '3411', 'name' => 'Նախորդ տարիների չբաշխված շահույթ / չծածկված վնաս (Retained Earnings)', 'type' => 'equity', 'subtype' => 'retained_earnings', 'desc' => 'Նախորդ ժամանակաշրջաններից կուտակված չբաշխված զուտ շահույթ կամ վնաս։'],
        ['code' => '3421', 'name' => 'Հաշվետու տարվա զուտ շահույթ (վնաս) (Current Year Net Profit/Loss)', 'type' => 'equity', 'subtype' => 'retained_earnings', 'desc' => 'Ընթացիկ ֆինանսական տարվա գործունեության զուտ արդյունք։'],

        // ԴԱՍ 4. ՈՉ ԸՆԹԱՑԻԿ ՊԱՐՏԱՎՈՐՈՒԹՅՈՒՆՆԵՐ (Non-Current Liabilities)
        ['code' => '4111', 'name' => 'Երկարաժամկետ բանկային վարկեր (Long-Term Bank Loans)', 'type' => 'liability', 'subtype' => 'long_term_liability', 'desc' => '1 տարուց ավելի մարման ժամկետ ունեցող բանկային վարկային պարտավորություններ։'],
        ['code' => '4211', 'name' => 'Երկարաժամկետ պարտավորություններ վարձակալության գծով (Long-Term Leases)', 'type' => 'liability', 'subtype' => 'long_term_liability', 'desc' => 'Ֆինանսական վարձակալության (լիզինգ) երկարաժամկետ պարտավորություններ։'],

        // ԴԱՍ 5. ԸՆԹԱՑԻԿ ՊԱՐՏԱՎՈՐՈՒԹՅՈՒՆՆԵՐ (Current Liabilities)
        ['code' => '5111', 'name' => 'Կարճաժամկետ բանկային վարկեր և օվերդրաֆտներ (Short-Term Bank Loans)', 'type' => 'liability', 'subtype' => 'current_liability', 'desc' => 'Կարճաժամկետ բանկային վարկեր և հաշվարկային հաշվի օվերդրաֆտներ։'],
        ['code' => '5211', 'name' => 'Կրեդիտորական պարտքեր գնումների գծով - Մատակարարներ (Accounts Payable)', 'type' => 'liability', 'subtype' => 'accounts_payable', 'desc' => 'Ստացված սննդամթերքի, խմիչքների և տնտեսական ապրանքների չվճարված հաշիվներ։'],
        ['code' => '5213', 'name' => 'Կրեդիտորական պարտքեր ծառայությունների գծով (Payables for Services)', 'type' => 'liability', 'subtype' => 'accounts_payable', 'desc' => 'Կոմունալ, տարածքի վարձակալության, ՏՏ և այլ ծառայությունների գծով պարտքեր։'],
        ['code' => '5231', 'name' => 'Ստացված ընթացիկ կանխավճարներ (Customer Advance Deposits)', 'type' => 'liability', 'subtype' => 'unearned_revenue', 'desc' => 'Բանկետների, միջոցառումների կամ սեղանների ամրագրման համար ստացված կանխավճարներ։'],
        ['code' => '5241', 'name' => 'Պարտքեր շահութահարկի գծով (Corporate Profit Tax Payable)', 'type' => 'liability', 'subtype' => 'tax_payable', 'desc' => 'ՀՀ ՊԵԿ պետական բյուջե վճարվելիք շահութահարկ։'],
        ['code' => '5242', 'name' => 'Պարտքեր եկամտային հարկի գծով (Income Tax Payable - Withheld)', 'type' => 'liability', 'subtype' => 'tax_payable', 'desc' => 'Աշխատակիցների աշխատավարձից պահված և բյուջե վճարվելիք եկամտային հարկ։'],
        ['code' => '5243', 'name' => 'Պարտքեր ավելացված արժեքի հարկի (ԱԱՀ) գծով (VAT Payable - Armenia SRC)', 'type' => 'liability', 'subtype' => 'tax_payable', 'desc' => 'Իրացումից հաշվարկված և բյուջե վճարման ենթակա ԱԱՀ։'],
        ['code' => '5248', 'name' => 'Պարտքեր դրոշմանիշային վճարների գծով (Military Insurance Stamp Duty)', 'type' => 'liability', 'subtype' => 'tax_payable', 'desc' => 'Զինծառայողների ապահովագրության հիմնադրամին վճարվելիք դրոշմանիշային վճարներ։'],
        ['code' => '5249', 'name' => 'Պարտքեր շրջանառության հարկի գծով (Turnover Tax Payable)', 'type' => 'liability', 'subtype' => 'tax_payable', 'desc' => 'Հանրային սննդի ոլորտում հաշվարկված և վճարվելիք շրջանառության հարկ։'],
        ['code' => '5251', 'name' => 'Պարտքեր պարտադիր կենսաթոշակային վճարների գծով (Mandatory Pension Payable)', 'type' => 'liability', 'subtype' => 'tax_payable', 'desc' => 'Աշխատակիցների պարտադիր կուտակային կենսաթոշակային վճարներ։'],
        ['code' => '5271', 'name' => 'Պարտքեր աշխատավարձի վճարման գծով (Net Salaries Payable)', 'type' => 'liability', 'subtype' => 'payroll_payable', 'desc' => 'Աշխատակիցներին վճարման ենթակա հաշվարկված զուտ աշխատավարձ։'],
        ['code' => '5311', 'name' => 'Թեյավճարների գծով ընթացիկ պարտավորություն (Waiter Tips Payable Pool)', 'type' => 'liability', 'subtype' => 'tips_payable', 'desc' => 'Հաճախորդներից քարտով կամ հաշվով հավաքագրված թեյավճարներ՝ անձնակազմին բաշխելու համար։'],

        // ԴԱՍ 6. ԵԿԱՄՈՒՏՆԵՐ (Revenue & Income)
        ['code' => '6111', 'name' => 'Հասույթ արտադրանքի վաճառքից (Food Sales Revenue)', 'type' => 'revenue', 'subtype' => 'sales_food', 'desc' => 'Խոհանոցում պատրաստված ուտեստների, աղցանների, աղանդերի իրացումից հասույթ։'],
        ['code' => '6112', 'name' => 'Հասույթ ապրանքների վաճառքից (Beverage & Bar Sales Revenue)', 'type' => 'revenue', 'subtype' => 'sales_beverage', 'desc' => 'Բարի խմիչքների, շշալցված ջրերի, հյութերի և վերավաճառքի ապրանքների հասույթ։'],
        ['code' => '6114', 'name' => 'Հասույթ ծառայությունների մատուցումից (Service Charge Revenue)', 'type' => 'revenue', 'subtype' => 'sales_service', 'desc' => 'Պարտադիր սպասարկման վճար (service fee) և սեփական առաքման վճարներ։'],
        ['code' => '6121', 'name' => 'Հետվերադարձված արտադրանք և ապրանքներ (Sales Returns & Allowances)', 'type' => 'revenue', 'subtype' => 'contra_revenue', 'desc' => 'Հետ վերադարձված կամ չեղարկված պատվերների կոնտրա-հասույթի հաշիվ։'],
        ['code' => '6123', 'name' => 'Տրամադրված առևտրային զեղչեր (Promotional Discounts Allowed)', 'type' => 'revenue', 'subtype' => 'contra_revenue', 'desc' => 'Ակցիաներով, պրոմո-կոդերով և լոյալության քարտերով տրամադրված զեղչեր։'],
        ['code' => '6143', 'name' => 'Եկամուտներ գույքագրման ավելցուկներից (Inventory & Till Overages)', 'type' => 'revenue', 'subtype' => 'other_income', 'desc' => 'Հերթափոխի փակման ժամանակ դրամարկղի կամ պահեստի գույքագրման ավելցուկներ։'],
        ['code' => '6144', 'name' => 'Եկամուտներ դահլիճի վարձակալությունից (Hall Rental & Event Income)', 'type' => 'revenue', 'subtype' => 'other_income', 'desc' => 'Հանդիսությունների սրահի վարձակալություն, ֆոտոսեսիաների և միջոցառումների եկամուտ։'],
        ['code' => '6146', 'name' => 'Այլ գործառնական եկամուտներ (Other Operating Ancillary Income)', 'type' => 'revenue', 'subtype' => 'other_income', 'desc' => 'Ռեստորանի հիմնական գործունեությանը հարակից այլ գործառնական եկամուտներ։'],
        ['code' => '6211', 'name' => 'Ֆինանսական եկամուտներ բանկային տոկոսներից (Interest Income)', 'type' => 'revenue', 'subtype' => 'other_income', 'desc' => 'Բանկային հաշիվների դրական մնացորդներից կամ ավանդներից հաշվեգրված տոկոսներ։'],

        // ԴԱՍ 7. ԾԱԽՍԵՐ (Expenses)
        ['code' => '7111', 'name' => 'Իրացված արտադրանքի ինքնարժեք (COGS - Food Cost)', 'type' => 'expense', 'subtype' => 'cost_of_goods_sold', 'desc' => 'Իրացված կերակրատեսակների պատրաստման համար ծախսված հումքի և նյութերի ինքնարժեք։'],
        ['code' => '7112', 'name' => 'Իրացված ապրանքների ինքնարժեք (COGS - Beverage Cost)', 'type' => 'expense', 'subtype' => 'cost_of_goods_sold', 'desc' => 'Իրացված բարի խմիչքների և վերավաճառքի ապրանքների ձեռքբերման ինքնարժեք։'],
        ['code' => '7114', 'name' => 'Մատուցված ծառայությունների ինքնարժեք (Cost of Services Rendered)', 'type' => 'expense', 'subtype' => 'cost_of_goods_sold', 'desc' => 'Մատուցված ծառայությունների գծով անմիջականորեն կրած ծախսումներ։'],
        ['code' => '7121', 'name' => 'Իրացման գծով աշխատակիցների աշխատավարձ (Delivery Staff Wages)', 'type' => 'expense', 'subtype' => 'operating_expense', 'desc' => 'Առաքիչների, առաքման համակարգողների և վաճառքի անձնակազմի աշխատավարձ։'],
        ['code' => '7124', 'name' => 'Փոխադրման և առաքման ծախսեր (Delivery Logistics & Packaging)', 'type' => 'expense', 'subtype' => 'operating_expense', 'desc' => 'Առաքման տրանսպորտային ծախսեր, վառելիք, առաքման մեկանգամյա տարաներ։'],
        ['code' => '7125', 'name' => 'Գովազդի և մարքեթինգի ծախսեր (Marketing & Advertising Expense)', 'type' => 'expense', 'subtype' => 'operating_expense', 'desc' => 'SMM առաջխաղացում, թիրախային գովազդ, պոլիգրաֆիա, մենյուների տպագրություն։'],
        ['code' => '7126', 'name' => 'Առաքման ծառայությունների միջնորդավճարներ (Delivery Partner Commissions)', 'type' => 'expense', 'subtype' => 'operating_expense', 'desc' => 'Glovo, Yandex Eats, Menu.am ագրեգատորների միջնորդավճարներ։'],
        ['code' => '7131', 'name' => 'Վարչական անձնակազմի աշխատանքի վճարման ծախսեր (Administrative Staff Salaries)', 'type' => 'expense', 'subtype' => 'operating_expense', 'desc' => 'Ռեստորանի կառավարիչների, հաշվապահի, տնօրենի և սպասարկող անձնակազմի աշխատավարձ։'],
        ['code' => '7132', 'name' => 'Հիմնական միջոցների մաշվածություն (Depreciation & Amortization Expense)', 'type' => 'expense', 'subtype' => 'operating_expense', 'desc' => 'Խոհանոցային սարքավորումների և գույքի ամսական մաշվածության ծախս։'],
        ['code' => '7133', 'name' => 'Տարածքի և գույքի վարձակալական ծախսեր (Rent & Facility Lease Expense)', 'type' => 'expense', 'subtype' => 'operating_expense', 'desc' => 'Ռեստորանի դահլիճի, խոհանոցի և պահեստային տարածքների վարձակալություն։'],
        ['code' => '7134', 'name' => 'Կոմունալ ծախսեր (Utilities - Electricity, Gas, Water)', 'type' => 'expense', 'subtype' => 'operating_expense', 'desc' => 'Էլեկտրաէներգիայի, բնական գազի, խմելու ջրի և աղբահանության ծախսեր։'],
        ['code' => '7135', 'name' => 'Նորոգման և սպասարկման ծախսեր (Repairs & Kitchen Maintenance)', 'type' => 'expense', 'subtype' => 'operating_expense', 'desc' => 'Խոհանոցի սալօջախների, սառնարանների, սանտեխնիկայի և օդափոխության նորոգում։'],
        ['code' => '7136', 'name' => 'Գրասենյակային, տնտեսական և մաքրության նյութեր (Cleaning & Supplies)', 'type' => 'expense', 'subtype' => 'operating_expense', 'desc' => 'Մաքրող նյութեր, ախտահանիչներ, սպասքի լվացման միջոցներ, տնտեսական ապրանքներ։'],
        ['code' => '7137', 'name' => 'Կապի, ինտերնետի և ՏՏ ծախսեր (Telecommunications & Software)', 'type' => 'expense', 'subtype' => 'operating_expense', 'desc' => 'Օպտիկամանրաթելային ինտերնետ, IP հեռախոսակապ, POS ամպային ծրագրեր։'],
        ['code' => '7138', 'name' => 'Բանկային ծառայությունների և էքվայրինգի ծախսեր (Bank & POS Fees)', 'type' => 'expense', 'subtype' => 'operating_expense', 'desc' => 'Առևտրային բանկերի սպասարկման վճարներ և POS քարտային էքվայրինգի տոկոսներ։'],
        ['code' => '7139', 'name' => 'Այլ վարչական ծախսեր և տուրքեր (Other Administrative & Licenses)', 'type' => 'expense', 'subtype' => 'operating_expense', 'desc' => 'Ալկոհոլի և ծխախոտի վաճառքի պետական տուրքեր, սանիտարական թույլտվություններ։'],
        ['code' => '7144', 'name' => 'Տույժեր, տուգանքներ և դատական ծախսեր (Fines & Penalties Expense)', 'type' => 'expense', 'subtype' => 'operating_expense', 'desc' => 'Հարկային կամ տեսչական տույժեր, տուգանքներ, ուշացման տոկոսներ։'],
        ['code' => '7145', 'name' => 'Գույքագրման պակասորդներ, փչացումից կորուստներ (Inventory Spoilage & Shrinkage)', 'type' => 'expense', 'subtype' => 'operating_expense', 'desc' => 'Սննդամթերքի փչացում, ժամկետանց ապրանքների դուրսգրում և դրամարկղի պակասորդ։'],
        ['code' => '7148', 'name' => 'Այլ գործառնական ծախսեր (Other Operating Expenses)', 'type' => 'expense', 'subtype' => 'operating_expense', 'desc' => 'Կազմակերպության հիմնական գործունեությանն առնչվող այլ գործառնական ծախսեր։'],
        ['code' => '7511', 'name' => 'Շահութահարկի գծով ծախս (Corporate Income Tax Expense)', 'type' => 'expense', 'subtype' => 'tax_expense', 'desc' => 'Հաշվետու ժամանակաշրջանի շահութահարկի գծով ընդհանուր ծախս։'],
        ['code' => '7512', 'name' => 'Շրջանառության հարկի գծով ծախս (Turnover Tax Expense)', 'type' => 'expense', 'subtype' => 'tax_expense', 'desc' => 'Հանրային սննդի գործունեությունից եռամսյակային շրջանառության հարկի ծախս։'],
    ];

    /**
     * Map legacy generic account codes to official RA Chart of Accounts codes for seamless backward compatibility.
     *
     * @var array<string, string>
     */
    public const LEGACY_ALIASES = [
        '1010' => '2511', // Cash in Registers -> 2511 Ազգային արժույթի դրամարկղ
        '1015' => '2512', // Cash in Safe -> 2512 Գլխավոր դրամարկղ / Պահարան
        '1020' => '2521', // Operating Bank Account -> 2521 Հաշվարկային հաշիվ
        '1030' => '2551', // Card Clearing -> 2551 Դրամական միջոցներ ճանապարհին (էքվայրինգ)
        '1040' => '2552', // Online Gateway Clearing -> 2552 Դրամական միջոցներ ճանապարհին (դարպասներ)
        '1200' => '2211', // Accounts Receivable -> 2211 Դեբիտորական պարտքեր վաճառքների գծով
        '1300' => '2111', // Inventory -> 2111 Հումք և նյութեր (պահեստ)
        '1400' => '2281', // Employee Advances -> 2281 Առհաշիվ տրված գումարներ
        '2010' => '5211', // Accounts Payable -> 5211 Կրեդիտորական պարտքեր գնումների գծով
        '2100' => '5311', // Tips Payable -> 5311 Թեյավճարների գծով պարտավորություն
        '2200' => '5271', // Net Salaries Payable -> 5271 Պարտքեր աշխատավարձի վճարման գծով
        '2300' => '5243', // Sales Tax / VAT -> 5243 Պարտքեր ԱԱՀ-ի գծով
        '2350' => '5242', // Payroll Taxes -> 5242 Պարտքեր եկամտային հարկի գծով
        '3010' => '3111', // Owner Equity -> 3111 Կանոնադրական (բաժնեհավաք) կապիտալ
        '3020' => '3411', // Retained Earnings -> 3411 Նախորդ տարիների չբաշխված շահույթ
        '4010' => '6111', // Food Sales -> 6111 Հասույթ արտադրանքի վաճառքից
        '4020' => '6112', // Beverage Sales -> 6112 Հասույթ ապրանքների վաճառքից
        '4030' => '6114', // Service Charge -> 6114 Հասույթ ծառայությունների մատուցումից
        '4100' => '6144', // Other Income -> 6144 Եկամուտներ դահլիճի վարձակալությունից և այլ գործառնություններից
        '4900' => '6121', // Sales Returns -> 6121 Հետվերադարձված արտադրանք և ապրանքներ
        '4910' => '6123', // Discounts Allowed -> 6123 Տրամադրված առևտրային զեղչեր
        '5010' => '7111', // Food COGS -> 7111 Իրացված արտադրանքի ինքնարժեք
        '5020' => '7112', // Beverage COGS -> 7112 Իրացված ապրանքների ինքնարժեք
        '5100' => '7131', // Wages Expense -> 7131 Վարչական անձնակազմի աշխատանքի վճարման ծախսեր
        '5150' => '7131', // Social Contributions
        '5200' => '7133', // Rent -> 7133 Տարածքի վարձակալական ծախսեր
        '5300' => '7134', // Utilities -> 7134 Կոմունալ ծախսեր
        '5400' => '7135', // Repairs -> 7135 Նորոգման և սպասարկման ծախսեր
        '5500' => '7136', // General & Cleaning Supplies -> 7136 Գրասենյակային, տնտեսական և մաքրության ծախսեր
        '5600' => '7125', // Marketing -> 7125 Գովազդի և մարքեթինգի ծախսեր
        '5700' => '7138', // Bank & Acquirer Fees -> 7138 Բանկային ծառայությունների և էքվայրինգի ծախսեր
        '5800' => '7145', // Cash Variance -> 7145 Գույքագրման պակասորդներ և կորուստներ
    ];

    /**
     * Default Financial Categories template aligned with Republic of Armenia Chart of Accounts.
     */
    public const DEFAULT_CATEGORIES = [
        ['name' => 'Food & Kitchen Ingredients', 'code' => 'FOOD_SUPPLIES', 'type' => 'expense', 'account_code' => '7111', 'color' => '#10b981', 'icon' => 'fa-utensils'],
        ['name' => 'Bar & Beverage Stock', 'code' => 'BAR_SUPPLIES', 'type' => 'expense', 'account_code' => '7112', 'color' => '#8b5cf6', 'icon' => 'fa-wine-bottle'],
        ['name' => 'Takeaway & Packaging', 'code' => 'PACKAGING', 'type' => 'expense', 'account_code' => '7124', 'color' => '#06b6d4', 'icon' => 'fa-box-open'],
        ['name' => 'Kitchen Equipment & Maintenance', 'code' => 'MAINTENANCE', 'type' => 'expense', 'account_code' => '7135', 'color' => '#f59e0b', 'icon' => 'fa-wrench'],
        ['name' => 'Utilities (Electric, Gas, Water)', 'code' => 'UTILITIES', 'type' => 'expense', 'account_code' => '7134', 'color' => '#06b6d4', 'icon' => 'fa-bolt'],
        ['name' => 'Cleaning & Sanitation Supplies', 'code' => 'CLEANING', 'type' => 'expense', 'account_code' => '7136', 'color' => '#3b82f6', 'icon' => 'fa-soap'],
        ['name' => 'Rent & Facility Leases', 'code' => 'RENT', 'type' => 'expense', 'account_code' => '7133', 'color' => '#8b5cf6', 'icon' => 'fa-building'],
        ['name' => 'Marketing, SMM & Promotions', 'code' => 'MARKETING', 'type' => 'expense', 'account_code' => '7125', 'color' => '#ec4899', 'icon' => 'fa-bullhorn'],
        ['name' => 'Delivery Service Commissions', 'code' => 'DELIVERY_COMMISSION', 'type' => 'expense', 'account_code' => '7126', 'color' => '#f97316', 'icon' => 'fa-motorcycle'],
        ['name' => 'Bank & Acquirer Card Fees', 'code' => 'BANK_FEES', 'type' => 'expense', 'account_code' => '7138', 'color' => '#64748b', 'icon' => 'fa-credit-card'],
        ['name' => 'Event Hall & Banquet Rental Income', 'code' => 'HALL_RENTAL', 'type' => 'income', 'account_code' => '6144', 'color' => '#10b981', 'icon' => 'fa-champagne-glasses'],
        ['name' => 'Catering & Service Fee Income', 'code' => 'CATERING', 'type' => 'income', 'account_code' => '6114', 'color' => '#06b6d4', 'icon' => 'fa-truck'],
    ];

    /**
     * Ensure the tenant has a complete standard Chart of Accounts.
     */
    public function initializeDefaultAccounts(Vendor $vendor): void
    {
        $currency = $vendor->currency ?: 'AMD';

        foreach (self::DEFAULT_ACCOUNTS as $acc) {
            ChartOfAccount::withoutGlobalScopes()->firstOrCreate(
                [
                    'vendor_id' => $vendor->id,
                    'code' => $acc['code'],
                ],
                [
                    'name' => $acc['name'],
                    'type' => $acc['type'],
                    'subtype' => $acc['subtype'],
                    'currency' => $currency,
                    'current_balance' => 0.00,
                    'is_system' => true,
                    'is_active' => true,
                    'description' => $acc['desc'],
                ]
            );
        }

        // Initialize default categories
        foreach (self::DEFAULT_CATEGORIES as $cat) {
            $account = ChartOfAccount::withoutGlobalScopes()
                ->where('vendor_id', $vendor->id)
                ->where('code', $cat['account_code'])
                ->first();

            if ($account) {
                FinancialCategory::withoutGlobalScopes()->firstOrCreate(
                    [
                        'vendor_id' => $vendor->id,
                        'code' => $cat['code'],
                    ],
                    [
                        'name' => $cat['name'],
                        'type' => $cat['type'],
                        'account_id' => $account->id,
                        'color' => $cat['color'],
                        'icon' => $cat['icon'],
                        'is_active' => true,
                    ]
                );
            }
        }
    }

    /**
     * Get account by standard RA code or legacy alias, auto-initializing if missing.
     */
    public function getAccountByCode(Vendor $vendor, string $code): ChartOfAccount
    {
        $effectiveCode = self::LEGACY_ALIASES[$code] ?? $code;

        // Try effective RA code first, then exact requested code
        $account = ChartOfAccount::where('vendor_id', $vendor->id)
            ->where(function ($q) use ($code, $effectiveCode) {
                $q->where('code', $effectiveCode)
                    ->orWhere('code', $code);
            })
            ->orderByRaw('CASE WHEN code = ? THEN 0 ELSE 1 END', [$effectiveCode])
            ->first();

        if (! $account) {
            $this->initializeDefaultAccounts($vendor);
            $account = ChartOfAccount::where('vendor_id', $vendor->id)
                ->where(function ($q) use ($code, $effectiveCode) {
                    $q->where('code', $effectiveCode)
                        ->orWhere('code', $code);
                })
                ->orderByRaw('CASE WHEN code = ? THEN 0 ELSE 1 END', [$effectiveCode])
                ->firstOrFail();
        }

        return $account;
    }

    /**
     * Generate the next sequential entry number for a vendor: JE-YYYY-XXXXX
     */
    public function generateEntryNumber(Vendor $vendor, ?Carbon $date = null): string
    {
        $year = ($date ?: now())->format('Y');
        $prefix = "JE-{$year}-";

        $latest = JournalEntry::withoutGlobalScopes()
            ->where('vendor_id', $vendor->id)
            ->where('entry_number', 'like', "{$prefix}%")
            ->orderBy('id', 'desc')
            ->value('entry_number');

        $nextSeq = 1;
        if ($latest && preg_match('/JE-\d{4}-(\d+)/', $latest, $matches)) {
            $nextSeq = (int) $matches[1] + 1;
        }

        return sprintf('JE-%s-%05d', $year, $nextSeq);
    }

    /**
     * Post an immutable balanced journal entry.
     *
     * @param array{
     *     entry_date?: string,
     *     source_type: string,
     *     source_id?: int|null,
     *     idempotency_key: string,
     *     description: string,
     *     location_id?: int|null,
     *     lines: array<int, array{
     *         account_id: int,
     *         type: 'debit'|'credit',
     *         amount: float|int|string,
     *         currency?: string,
     *         exchange_rate?: float,
     *         location_id?: int|null,
     *         memo?: string|null
     *     }>
     * } $data
     */
    public function postJournalEntry(Vendor $vendor, array $data, ?User $user = null): JournalEntry
    {
        $idempotencyKey = $data['idempotency_key'] ?? (string) Str::uuid();

        // Idempotency check: if entry already exists, return it safely without duplicate posting
        $existing = JournalEntry::where('vendor_id', $vendor->id)
            ->where('idempotency_key', $idempotencyKey)
            ->with('lines.account')
            ->first();

        if ($existing) {
            return $existing;
        }

        $linesData = $data['lines'] ?? [];
        if (count($linesData) < 2) {
            throw ValidationException::withMessages([
                'lines' => 'A journal entry must contain at least 2 lines (debit and credit).',
            ]);
        }

        $entryDate = isset($data['entry_date']) ? Carbon::parse($data['entry_date']) : Carbon::now();

        // Verify that the entry date is not within a closed accounting period
        $closedPeriod = AccountingPeriod::where('vendor_id', $vendor->id)
            ->where('status', 'closed')
            ->where('start_date', '<=', $entryDate->toDateString())
            ->where('end_date', '>=', $entryDate->toDateString())
            ->first();

        if ($closedPeriod) {
            throw ValidationException::withMessages([
                'entry_date' => "Cannot post transactions into closed accounting period '{$closedPeriod->name}'.",
            ]);
        }

        // Validate Double-Entry balancing invariant
        $totalDebit = 0.00;
        $totalCredit = 0.00;
        $baseCurrency = $vendor->currency ?: 'AMD';

        foreach ($linesData as $index => $line) {
            $amount = round((float) ($line['amount'] ?? 0), 2);
            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    "lines.{$index}.amount" => 'Line amount must be greater than zero.',
                ]);
            }

            $type = strtolower($line['type'] ?? '');
            if (! in_array($type, ['debit', 'credit'], true)) {
                throw ValidationException::withMessages([
                    "lines.{$index}.type" => 'Line type must be debit or credit.',
                ]);
            }

            $rate = (float) ($line['exchange_rate'] ?? 1.000000);
            $baseAmount = round($amount * $rate, 2);

            if ($type === 'debit') {
                $totalDebit += $baseAmount;
            } else {
                $totalCredit += $baseAmount;
            }
        }

        $totalDebit = round($totalDebit, 2);
        $totalCredit = round($totalCredit, 2);

        // Balancing Invariant Check down to 0.01 precision
        if (bccomp((string) $totalDebit, (string) $totalCredit, 2) !== 0) {
            throw ValidationException::withMessages([
                'lines' => "Unbalanced Journal Entry: Total Debit ({$totalDebit}) does not equal Total Credit ({$totalCredit}).",
            ]);
        }

        return DB::transaction(function () use ($vendor, $data, $entryDate, $linesData, $totalDebit, $totalCredit, $idempotencyKey, $user, $baseCurrency) {
            $entryNumber = $this->generateEntryNumber($vendor, $entryDate);

            // Find matching open accounting period if any
            $period = AccountingPeriod::where('vendor_id', $vendor->id)
                ->where('status', 'open')
                ->where('start_date', '<=', $entryDate->toDateString())
                ->where('end_date', '>=', $entryDate->toDateString())
                ->first();

            // Compute cryptographic HMAC-SHA256 hash chain
            $lastEntry = JournalEntry::where('vendor_id', $vendor->id)
                ->where('status', 'posted')
                ->whereNotNull('entry_hash')
                ->orderBy('id', 'desc')
                ->first();

            $previousHash = $lastEntry?->entry_hash ?? hash('sha256', "GENESIS-{$vendor->id}");
            $appSecret = config('app.key') ?: 'qrmenu-finance-secret';
            $debitStr = number_format($totalDebit, 2, '.', '');
            $creditStr = number_format($totalCredit, 2, '.', '');
            $dataToSign = "{$vendor->id}|{$entryNumber}|{$entryDate->toDateString()}|{$debitStr}|{$creditStr}|{$previousHash}";
            $entryHash = hash_hmac('sha256', $dataToSign, $appSecret);

            $entry = JournalEntry::create([
                'vendor_id' => $vendor->id,
                'location_id' => $data['location_id'] ?? null,
                'accounting_period_id' => $period?->id,
                'entry_number' => $entryNumber,
                'entry_date' => $entryDate->toDateString(),
                'source_type' => $data['source_type'],
                'source_id' => $data['source_id'] ?? null,
                'idempotency_key' => $idempotencyKey,
                'status' => 'posted',
                'is_reversal' => false,
                'description' => $data['description'],
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'previous_hash' => $previousHash,
                'entry_hash' => $entryHash,
                'created_by' => $user?->id ?? auth()->id(),
                'posted_at' => Carbon::now(),
            ]);

            $affectedAccountIds = [];

            foreach ($linesData as $line) {
                $amount = round((float) $line['amount'], 2);
                $rate = (float) ($line['exchange_rate'] ?? 1.000000);
                $baseAmount = round($amount * $rate, 2);

                JournalEntryLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id' => $line['account_id'],
                    'location_id' => $line['location_id'] ?? $entry->location_id,
                    'type' => strtolower($line['type']),
                    'amount' => $amount,
                    'currency' => $line['currency'] ?? $baseCurrency,
                    'exchange_rate' => $rate,
                    'amount_base_currency' => $baseAmount,
                    'memo' => $line['memo'] ?? null,
                ]);

                $affectedAccountIds[] = $line['account_id'];
            }

            // Update real-time balances for affected accounts
            foreach (array_unique($affectedAccountIds) as $accId) {
                $account = ChartOfAccount::find($accId);
                $account?->recalculateBalance();
            }

            FinancialAuditLog::record(
                vendor: $vendor,
                action: 'journal_entry_posted',
                auditable: $entry,
                user: $user,
                newValues: [
                    'entry_number' => $entry->entry_number,
                    'total' => $totalDebit,
                    'source_type' => $entry->source_type,
                    'source_id' => $entry->source_id,
                ],
                amount: $totalDebit,
                locationId: $entry->location_id
            );

            return $entry->load('lines.account');
        });
    }

    /**
     * Create an immutable reversal journal entry to correct a posted transaction.
     */
    public function createReversalEntry(
        JournalEntry $entry,
        ?string $reason = null,
        ?User $user = null
    ): JournalEntry {
        if ($entry->isReversed()) {
            throw ValidationException::withMessages([
                'entry' => 'This journal entry has already been reversed.',
            ]);
        }

        $entry->load('lines');
        $vendor = $entry->vendor;

        return DB::transaction(function () use ($entry, $reason, $user, $vendor) {
            $reversalNumber = $this->generateEntryNumber($vendor);
            $idempotencyKey = "reversal-{$entry->id}-".Str::random(10);

            $reversalEntry = JournalEntry::create([
                'vendor_id' => $entry->vendor_id,
                'location_id' => $entry->location_id,
                'accounting_period_id' => $entry->accounting_period_id,
                'entry_number' => $reversalNumber,
                'entry_date' => Carbon::now()->toDateString(),
                'source_type' => $entry->source_type,
                'source_id' => $entry->source_id,
                'idempotency_key' => $idempotencyKey,
                'status' => 'posted',
                'is_reversal' => true,
                'reversal_of_entry_id' => $entry->id,
                'description' => "Reversal of {$entry->entry_number}: ".($reason ?: $entry->description),
                'total_debit' => $entry->total_credit, // Swapped
                'total_credit' => $entry->total_debit,
                'created_by' => $user?->id ?? auth()->id(),
                'posted_at' => Carbon::now(),
            ]);

            $affectedAccountIds = [];

            foreach ($entry->lines as $line) {
                // Invert type: debit becomes credit, credit becomes debit
                $invertedType = $line->isDebit() ? 'credit' : 'debit';

                JournalEntryLine::create([
                    'journal_entry_id' => $reversalEntry->id,
                    'account_id' => $line->account_id,
                    'location_id' => $line->location_id,
                    'type' => $invertedType,
                    'amount' => $line->amount,
                    'currency' => $line->currency,
                    'exchange_rate' => $line->exchange_rate,
                    'amount_base_currency' => $line->amount_base_currency,
                    'memo' => "Reversal line for #{$entry->entry_number}",
                ]);

                $affectedAccountIds[] = $line->account_id;
            }

            // Mark original entry as reversed
            $entry->update(['status' => 'reversed']);

            // Recalculate balances
            foreach (array_unique($affectedAccountIds) as $accId) {
                $account = ChartOfAccount::find($accId);
                $account?->recalculateBalance();
            }

            FinancialAuditLog::record(
                vendor: $vendor,
                action: 'journal_entry_reversed',
                auditable: $reversalEntry,
                user: $user,
                oldValues: ['original_entry_number' => $entry->entry_number],
                newValues: ['reversal_entry_number' => $reversalEntry->entry_number, 'reason' => $reason],
                amount: (float) $reversalEntry->total_debit
            );

            return $reversalEntry->load('lines.account');
        });
    }

    /**
     * Lock an accounting period preventing retroactive modifications.
     */
    public function closeAccountingPeriod(
        AccountingPeriod $period,
        User $user,
        ?string $notes = null
    ): AccountingPeriod {
        if ($period->isClosed()) {
            throw ValidationException::withMessages([
                'period' => 'This accounting period is already closed.',
            ]);
        }

        $period->update([
            'status' => 'closed',
            'closed_by' => $user->id,
            'closed_at' => Carbon::now(),
            'notes' => $notes ?: $period->notes,
        ]);

        FinancialAuditLog::record(
            vendor: $period->vendor,
            action: 'accounting_period_closed',
            auditable: $period,
            user: $user,
            newValues: ['period_name' => $period->name, 'closed_at' => $period->closed_at->toIso8601String()]
        );

        return $period->fresh();
    }
}
