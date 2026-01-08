<?php

namespace App\Http\Controllers\V1\Portal\GroupClient;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class QuotationController extends Controller
{
    //

    //create quotation

    public function createGroupQuotation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'is_random_client' => 'nullable|boolean',
            //'broker_id' => 'nullable',
            'Quotation_name' => 'nullable',
            'product_name' => 'required',
            'total_members' => 'nullable',
            'total_salary' => 'nullable',
            'salary_multiplier' => 'nullable', //lcf
            'fixed_sum_assured' => 'nullable', //sum_assured
            'premium_rate' => 'nullable', //PremRate
            'death_cover_premium' => 'nullable', //lc_premium
            'rider_premium' => 'nullable', //riderPremium
            'phcf_amount' => 'nullable', //PHCFAmt
            'stamp_duty' => 'nullable', //StampDutyAmt
            'total_risk_premium' => 'nullable', //total_prem
            'unit_rate' => 'nullable', //unit_rate
            'FreeCoverLimit' => 'nullable', //FreeCoverLimit
            'portal_user_id' => 'nullable', // user making the request
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'cover_period' => 'nullable',
            'client_type' => 'nullable',
            'client_address' => 'nullable',
            'client_phone_number' => 'nullable',
            'with_riders' => 'nullable|boolean', //with riders
            'with_categories' => 'nullable|boolean', //with categories
            'is_existing_client' => 'nullable|boolean', //is existing client
            'occupation_classes' => 'nullable', //OccupationClasses glifeOccupClassInfo
            'pay_mode' => 'nullable', //PayMode glifepaymodeinfo
            'email' => 'nullable',
            'mobile_number' => 'nullable',
            'riders' => 'array',
            'riders.*.rider_code' => 'required',
            'riders.*.distribution_type' => 'required', //DistributionType
            'riders.*.rider_benefit' => 'nullable', //sa ie rider benefit
            'riders.*.perc_payable' => 'nullable', //Perc Applicable
            'riders.*.premium_amount' => 'nullable', //Premium Amt
            'riders.*.benefit_level' => 'nullable', //SaPerMember
            'riders.*.salary_multiplier' => 'nullable', //LifeCoverFactor
            'riders.*.use_rate_table' => 'nullable|boolean', //UseRateTable
            'riders.*.fixed_premium' => 'nullable|boolean', //FixedPremium
            'riders.*.premium_rate' => 'nullable', //IsSalaryBased
            'riders.*.scheme_benefit_limit' => 'nullable', //PremRate

        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid request data',
                'data' => $validator->errors()
            ], 400);
        }

        try {

            $is_random_client = $request->is_random_client ?? false;
            //$broker_id = $request->broker_id;
            $quotation_name = $request->Quotation_name;
            $total_members = $request->total_members ?? 0;
            $total_salary = $request->total_salary ?? 0;
            $salary_multiplier = $request->salary_multiplier ?? 0;
            $fixed_sum_assured = $request->fixed_sum_assured ?? 0;
            $premium_rate = $request->premium_rate ?? 0;
            $death_cover_premium = $request->death_cover_premium ?? 0;
            $rider_premium = $request->rider_premium ?? 0;
            $phcf_amount = $request->phcf_amount ?? 0;
            $stamp_duty = $request->stamp_duty ?? 0;
            $total_risk_premium = $request->total_risk_premium ?? 0;
            $unit_rate = $request->unit_rate ?? 0;
            $free_cover_limit = $request->FreeCoverLimit ?? 0;
            $login_user_id = $request->input('portal_user_id') ?? null;
            $start_date = $request->StartDate;
            $end_date = $request->EndDate;
            $cover_period = $request->cover_period ?? 0;
            $ins_product = $request->product_name;
            $client_type = $request->client_type ?? null;
            $client_address = $request->client_address ?? null;
            $client_phone_number = $request->client_phone_number ?? null;
            $with_riders = $request->with_riders ?? false;
            $with_categories = $request->with_categories ?? false;
            $is_existing_client = $request->is_existing_client ?? false;
            $occupation_classes = $request->occupation_classes ?? null;
            $quotation_date = date('Y-m-d H:i:s');
            $email = $request->email ?? null;
            $mobile_number = $request->mobile_number ?? null;

            $Avg_Age = $request->Avg_Age ?? null;
            $PackageId = $request->PackageId ?? null;

            // insert to QuotationRequest table and get the id

            $this->britam_db->beginTransaction();

            // if it is random client, get the broker id from the portalloginuser

            $broker_id = null;
            $cp_id = null;
            $contact_persons_id = null;

            if ($login_user_id != null && $is_random_client == false) {

                $email = null;
                $mobile_number = null;

                $broker_id = $this->britam_db->table('PortalUserLoginInfo')->select('Broker')->where('Id', $login_user_id)->first();

                if ($broker_id == null) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid broker'
                    ], 400);
                }

                $broker_id = $broker_id->Broker;

                //get contactpersonid from the portalloginuser
                $cp_id = $this->britam_db->table('PortalUserLoginInfo')->select('ContactPerson')->where('Id', $login_user_id)->first();

                if ($cp_id == null) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid contact person'
                    ], 400);
                }

                $contact_persons_id = $cp_id->ContactPerson;
            }

            if ($is_random_client == true && $email == null && $mobile_number == null) {
                // throw an error to include the  email and mobile number
                return response()->json([
                    'success' => false,
                    'message' => 'Please provide email and mobile number for the random client'
                ], 400);

            }

            if ($is_existing_client == false && $quotation_name == null) {
                // throw an error to include the client name
                return response()->json([
                    'success' => false,
                    'message' => 'Please provide client name'
                ], 400);
            }

            $status = $this->britam_db->table('EndorsementRequestStatus')->where('IsPending', 1)->first();
            $request_id = $this->britam_db->table('QuotationRequest')->insertGetId([
                // quotation date
                'BrokerId' => $broker_id,
                'IntermContact' => $contact_persons_id,
                'QuotationDate' => $quotation_date,
                'ProductName' => $ins_product,
                'ClientType' => $client_type,
                'ClientAddress' => $client_address,
                'ClientPhoneNumber' => $client_phone_number,
                'StartDate' => $start_date,
                'EndDate' => $end_date,
                'CoverPeriod' => $cover_period,
                'Quotation_name' => $quotation_name, // client Name
                'total_members' => $total_members,
                'total_salary' => $total_salary,
                'lcf' => $salary_multiplier,
                'sum_assured' => $fixed_sum_assured,
                'PremRate' => $premium_rate,
                'lc_premium' => $death_cover_premium,
                'riderPremium' => $rider_premium,
                'PHCFAmt' => $phcf_amount,
                'StampDutyAmt' => $stamp_duty,
                'total_prem' => $total_risk_premium,
                'unit_rate' => $unit_rate,
                'FreeCoverLimit' => $free_cover_limit,
                'IsExistingClient' => $is_existing_client,
                'OccupationClass' => $occupation_classes,
                'PayMode' => $request->pay_mode,
                'With_riders' => $with_riders,
                'With_categories' => $with_categories,
                'EmailOfUserMakingChanges' => $email,
                'MobileNumberOfUserMakingChanges' => $mobile_number,
                'created_on' => date('Y-m-d H:i:s'),
                'created_by' => 'API',
                'Status' => $status->Id,

                'Avg_Age' => $Avg_Age,
                'PackageId' => $PackageId,
                'IsDetailedQuote' => 1
            ]);

            //TODO - Insert into TravelMembers if travel
            //$IsTravelProduct = $status = $this->britam_db->table('glifeclass')->where('IsTravelInsurance', 1)->first();
            if($ins_product == "11" || $ins_product == 11){
                //$riders = $request->riders ?? [];
                $traveler = $request->traveler;
                for ($i = 0; $i < sizeof($traveler); $i++) {
                    $traveler[$i]['QuotationRequestId'] = $request_id;
                    $this->britam_db->table('QuotReqTravelMembers')->insertGetId($traveler[$i]);
                }
            }

            // insert to QuotationRequestRiders table

            $riders = $request->riders ?? [];

            if (sizeof($riders) > 0) {

                foreach ($riders as $rider) {
                    $distribution_type = $rider['distribution_type'];

                    if ($distribution_type == 1) { //Fixed
                        $fixed_sum = 1;
                        $perc_of_sa = 0;
                        $salary_based = 0;
                    } elseif ($distribution_type == 2) { // Percentage
                        $fixed_sum = 0;
                        $perc_of_sa = 1;
                        $salary_based = 0;
                    } elseif ($distribution_type == 3) { // Salary Based  
                        $fixed_sum = 0;
                        $perc_of_sa = 0;
                        $salary_based = 1;
                    }

                    $rider_id = $rider['rider_code'];
                    $fixed_premium = $rider['fixed_premium'] ?? 0;
                    $sum_assured = $rider['rider_benefit'] ?? 0;
                    $perc_payable = $rider['perc_payable'] ?? 0;
                    $premium_amount = $rider['premium_amount'] ?? 0;
                    $benefit_level = $rider['benefit_level'] ?? 0;
                    $salary_multiplier = $rider['salary_multiplier'] ?? 0;
                    $rate_table_usage = $rider['use_rate_table'] ?? 0;
                    $prem_rate = $rider['premium_rate'] ?? 0;
                    $scheme_benefit_limit = $rider['scheme_benefit_limit'] ?? 0;

                    if ($fixed_sum == 1) { // is benefit fixed
                        $salary_multiplier = 0;
                        $perc_payable = 0;
                    }

                    if ($perc_of_sa == 1) { // is % of SA
                        $salary_multiplier = 0;
                        $benefit_level = 0;
                    }

                    if ($salary_based == 1) { // is salary based
                        $benefit_level = 0;
                        $perc_payable = 0;
                    }

                    if ($fixed_premium == 1) { // is fixed premium
                        $premium_amount = 0;
                        $prem_rate = 0;
                    }

                    if ($rate_table_usage == 1) { // use rate table
                        $prem_rate = 0;
                    }

                    $this->britam_db->table('QuotationRequestRider')->insert([
                        'QuotationNumber' => $request_id,
                        'plan_rider_id' => $rider_id,
                        'fixed_sum' => $fixed_sum,
                        'perc_of_sa' => $perc_of_sa,
                        'FixedPremium' => $fixed_premium,
                        'sa' => $sum_assured,
                        'perc_payable' => $perc_payable,
                        'Premium' => $premium_amount,
                        'SaPerMember' => $benefit_level,
                        'LifeCoverFactor' => $salary_multiplier,
                        'IsSalaryBased' => $salary_based,
                        'UseRateTable' => $rate_table_usage,
                        'PremRate' => $prem_rate,
                        'SchemeBenefitLimit' => $scheme_benefit_limit,
                        'created_on' => date('Y-m-d H:i:s'),
                        'created_by' => 'API'
                    ]);
                }
            }

            $this->britam_db->commit();

            return response()->json([
                'success' => true,
                'message' => 'Quotation created successfully',
                'data' => ['quotation_request_id' => $request_id]
            ], 201);


        } catch (\Throwable $th) {
            //throw $th;
            $this->britam_db->rollBack();
            return response()->json([
                'success' => false,
                'message' => 'An error occured',
                'data' => $th->getMessage()
            ], 500);
        }
    }

    //update quotation

    public function updateGroupQuotation(Request $request)
    {

    }

    //view quotation

    public function viewGroupQuotation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'quotation_request_id' => 'nullable',
            'broker_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid request data',
                'data' => $validator->errors()
            ], 400);
        }

        try {

            $quotation_request_id = $request->quotation_request_id ?? null;
            $broker_id = $request->broker_id;

            if ($quotation_request_id) {

                $quotation = $this->britam_db->table('QuotationRequest as q')
                    ->leftJoin('QuotationRequestRider as r', 'q.Id', '=', 'r.QuotationNumber')
                    ->where('q.Id', $quotation_request_id)
                    ->select('q.*', 'r.*')
                    ->first();

            } else {

                // SELECT q.*, r.*
                // FROM QuotationRequest q
                // LEFT JOIN QuotationRequestRider r ON q.Id = r.QuotationNumber
                // WHERE q.BrokerId = 24;

                $quotation = $this->britam_db->table('QuotationRequest as q')
                    ->leftJoin('QuotationRequestRider as r', 'q.Id', '=', 'r.QuotationNumber')
                    ->where('q.BrokerId', $broker_id)
                    ->select('q.*', 'r.*')
                    ->get();

            }

            if (!$quotation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Quotation not found',
                    'data' => null
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Quotation retrieved successfully',
                'data' => $quotation
            ], 200);

        } catch (\Throwable $th) {
            //throw $th;
            return response()->json([
                'success' => false,
                'message' => 'An error occured',
                'data' => $th->getMessage()
            ], 500);
        }

    }
}
