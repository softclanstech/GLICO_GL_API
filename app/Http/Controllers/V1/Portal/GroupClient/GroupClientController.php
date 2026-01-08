<?php

namespace App\Http\Controllers\V1\Portal\GroupClient;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class GroupClientController extends Controller
{
    //

    public function generateClientNumber()
    {
        $client_number = $this->britam_db->table('companyinfo')->max('NextClientNumberGroup');
        $new_client_number = $client_number + 1;
        $this->britam_db->table('companyinfo')->update(['NextClientNumberGroup' => $new_client_number]);
        return sprintf('%06d', $client_number);
    }

    private function isSchemeInClientSchemesAccess($clientScheme, $contact_person_id)
    {
        return $this->britam_db->table('ClientSchemesAccess')
            ->where('Scheme', $clientScheme->schemeID)
            ->where('PortalUser', $contact_person_id)
            ->exists();
    }

    private function addSchemeToClientSchemesAccess($clientScheme, $portal_contact_person)
    {
        $this->britam_db->table('ClientSchemesAccess')->insert([
            'PortalUser' => $portal_contact_person,
            'Scheme' => $clientScheme->schemeID,
            'AllowAccess' => 1,
            'created_by' => 'API',
            'created_on' => now(),
        ]);
    }


    private function isDataChanged($existingData, $newData)
    {
        foreach ($newData as $key => $value) {
            if ($existingData->$key != $value) {
                return true;
            }
        }

        return false;
    }

    public function getGroupClients(Request $request)
    {
        try {
            $gc_email = $request->input('email');
            $gc_id = $request->input('gc_id');
            $intermediary_id = $request->input('intermediary_id');

            if ($gc_email != null) {
                $results = $this->britam_db->table('glifeclientinfo')->select("*")->where("email", $gc_email)->get();
            } else if ($gc_id != null) {
                $results = $this->britam_db->table('glifeclientinfo')->select("*")->where("Id", $gc_id)->get();
            } else if ($intermediary_id != null) {
                $results = $this->britam_db->table('intermediaryinfo')->select("*")->where("id", $intermediary_id)->get();
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid request data. Please provide either email, gc_id or intermediary_id',
                    'data' => []
                ], 400);
            }

            if ($results != null) {
                return response()->json([
                    'success' => true,
                    'message' => 'Clients fetched successfully',
                    'data' => $results
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'No clients found',
                    'data' => []
                ], 404);
            }
        } catch (\Throwable $th) {
            //throw $th;
            return response()->json([
                'success' => false,
                'message' => 'Error fetching clients' . $th->getMessage()
            ], 500);
        }
    }

    public function setGroupClient(Request $request)
    {
        $validator = Validator::make($request->all(), [

            //basic info
            //'gc_id' => 'required',
            'gc_name' => 'required',
            'client_type' => 'nullable', //always set to 1 for corporate institutions
            'registration_date' => 'date|required',
            'registration_number' => 'required',
            'country_of_registration' => 'required',
            'county' => 'required', //required if country_of_registration is 001
            'annual_turnover' => 'nullable',
            'nationality' => 'required',
            'Is_US_FATCA_Indicia' => 'boolean|required',
            'has_multiple_schemes' => 'boolean|required',

            //business
            'occupaion_class' => 'nullable',
            'nature_of_business' => 'nullable',
            'access_type' => 'required', //normal or restricted

            //statutory info
            'pin_number' => 'required',
            'id_type' => 'required',
            'document_number' => 'required',
            'vendor_number' => 'required',

            //contact_details
            'physical_address' => 'nullable',
            'postal_address' => 'required',
            'email_address' => 'required',
            'mobile_number' => 'required',

            //screening
            'screening' => 'boolean|required',
            'flagged' => 'boolean|required',
            'sanctioned' => 'boolean|required',
            'is_politically_exposed' => 'required',
            'risk_classification' => 'nullable',

            //ESG
            'esg_esms_screening_status' => 'nullable',

            //UFAA
            'ufaa_dormancy' => 'nullable',

        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid request data',
                'data' => $validator->errors()
            ], 400);
        }

        try {
            $client_type = 1;
            $flagged = $request->input('flagged');
            $sanctioned = $request->input('sanctioned');
            $politically_exposed = $request->input('is_politically_exposed');
            $risk_classification = $request->input('risk_classification');
            $annual_turn_over = $request->input('annual_turnover');

            if ($flagged == 0) {
                $sanctioned = 0;
                $politically_exposed = null;
            } elseif ($flagged == 1) {
                $risk_classification = ($sanctioned == 1) ? 1 : 3;
            }

            $risk_classification = $risk_classification ?? 3;

            if ($annual_turn_over == null) {
                $annual_turn_over = 0;
            }

            $client_number = $this->generateClientNumber();
            Log::channel('corporate_api')->info('Generated client number: ' . $client_number);

            $group_client_data = [
                'client_number' => $client_number,
                'name' => $request->input('gc_name'),
                'client_type' => $client_type,
                'DateOfRegistration' => $request->input('registration_date'),
                'RegistrationNumber' => $request->input('registration_number'),
                'CountryOfRegistration' => $request->input('country_of_registration'),
                'County' => $request->input('county'),
                'AnnualTurnover' => $annual_turn_over,
                'Nationality' => $request->nationality,
                'IsFATCAIndicia' => $request->input('Is_US_FATCA_Indicia'),
                'HasMultipleSchemes' => $request->input('has_multiple_schemes'),
                'OccupClass' => $request->input('occupaion_class'), //need a lookup table for this
                'NatureOfBusiness' => $request->input('nature_of_business'), // need a lookup table for this
                'AccessType' => $request->input('access_type'), //need a lookup table for this
                'pin_no' => $request->input('pin_number'),
                'IdType' => $request->input('id_type'), //need a lookup table for this
                'IdNumber' => $request->input('document_number'),
                'VendorNumber' => $request->input('vendor_number'),
                'PhysicalAddress' => $request->input('physical_address'),
                'address' => $request->input('postal_address'),
                'email' => $request->input('email_address'),
                'mobile' => $request->input('mobile_number'),
                'Screening' => $request->input('screening'),
                'Flagged' => $flagged,
                'Sanctions' => $sanctioned,
                'IsPoliticallyExposed' => $politically_exposed, //need a lookup table for this ->PEP STATUS
                'RiskClassification' => $risk_classification,
                'ESMSScreeningStatus' => $request->input('esg_esms_screening_status'), //need a lookup table for this ESGStatus
                'UFAADormancyStatus' => $request->input('ufaa_dormancy'), //need a lookup table for this UFAADormancyStatus
                'Status' => '001',
                'QuoteID' => '0',
                'RecordSaved' => 1,
                'created_by' => 'API',
                'created_on' => date('Y-m-d H:i:s'),
            ];

            Log::channel('corporate_api')->info('Group client data: ' . json_encode($group_client_data));


            $result = $this->britam_db->table('glifeclientinfo')->insert($group_client_data);

            if ($result) {
                return response()->json([
                    'success' => true,
                    'message' => 'Group client created successfully',
                    'client_number' => $client_number
                ], 201);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Error creating group client'
                ], 500);
            }
        } catch (\Throwable $th) {
            //throw $th;
            return response()->json([
                'success' => false,
                'message' => 'Error creating group client' . $th->getMessage()
            ], 500);
        }
    }

    public function updateGroupClient(Request $request)
    {
        $validator = Validator::make($request->all(), [

            //basic info
            'gc_id' => 'required',

        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid request data',
                'data' => $validator->errors()
            ], 400);
        }

        $check_approval_status = $this->britam_db->table('glifeclientinfo')->select('IsApproved')->where('Id', $request->input('gc_id'))->first();

        if ($check_approval_status->IsApproved == 1) {
            return response()->json([
                'success' => false,
                'message' => 'Client has already been approved'
            ], 400);
        }

        try {

            $client_type = 1;
            $flagged = $request->input('flagged');
            $sanctioned = $request->input('sanctioned');
            $politically_exposed = $request->input('is_politically_exposed');
            $risk_classification = $request->input('risk_classification');
            $annual_turn_over = $request->input('annual_turnover');

            if ($flagged == 0) {
                $sanctioned = 0;
                $politically_exposed = null;
            } elseif ($flagged == 1) {
                $risk_classification = ($sanctioned == 1) ? 1 : 3;
            }

            $risk_classification = $risk_classification ?? 3;

            if ($annual_turn_over == null) {
                $annual_turn_over = 0;
            }

            $group_client_data = [
                'name' => $request->input('gc_name'),
                'client_type' => $client_type,
                'DateOfRegistration' => $request->input('registration_date'),
                'RegistrationNumber' => $request->input('registration_number'),
                'CountryOfRegistration' => $request->input('country_of_registration'),
                'County' => $request->input('county'),
                'AnnualTurnover' => $request->input('annual_turnover'),
                'Nationality' => $request->nationality,
                'IsFATCAIndicia' => $request->input('Is_US_FATCA_Indicia'),
                'HasMultipleSchemes' => $request->input('has_multiple_schemes'),
                'OccupClass' => $request->input('occupaion_class'), //need a lookup table for this
                'NatureOfBusiness' => $request->input('nature_of_business'), // need a lookup table for this
                'AccessType' => $request->input('access_type'), //need a lookup table for this
                'pin_no' => $request->input('pin_number'),
                'IdType' => $request->input('id_type'), //need a lookup table for this
                'IdNumber' => $request->input('document_number'),
                'VendorNumber' => $request->input('vendor_number'),
                'PhysicalAddress' => $request->input('physical_address'),
                'address' => $request->input('postal_address'),
                'email' => $request->input('email_address'),
                'mobile' => $request->input('mobile_number'),
                'Screening' => $request->input('screening'),
                'Flagged' => $flagged,
                'Sanctions' => $sanctioned,
                'IsPoliticallyExposed' => $politically_exposed, //need a lookup table for this ->PEP STATUS
                'RiskClassification' => $risk_classification,
                'ESMSScreeningStatus' => $request->input('esg_esms_screening_status'), //need a lookup table for this ESGStatus
                'UFAADormancyStatus' => $request->input('ufaa_dormancy'), //need a lookup table for this UFAADormancyStatus
                'dola' => date('Y-m-d H:i:s'),
                'altered_by' => 'API',
            ];


            foreach (['name', 'client_type', 'DateOfRegistration', 'CountryOfRegistration', 'County', 'AnnualTurnover', 'Nationality', 'IsFATCAIndicia', 'HasMultipleSchemes', 'OccupClass', 'NatureOfBusiness', 'AccessType', 'pin_no', 'IdType', 'IdNumber', 'VendorNumber', 'PhysicalAddress', 'address', 'email', 'mobile', 'Screening', 'Flagged', 'Sanctions', 'IsPoliticallyExposed', 'RiskClassification', 'ESMSScreeningStatus', 'UFAADormancyStatus', 'dola', 'altered_by'] as $field) {
                if ($group_client_data[$field] !== null && $group_client_data[$field] !== '') {
                    $results = $this->britam_db->table('glifeclientinfo')->where('Id', $request->input('gc_id'))->update([$field => $group_client_data[$field]]);
                }
            }

            if ($results) {
                return response()->json([
                    'success' => true,
                    'message' => 'Group client updated successfully'
                ], 201);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Error updating group client'
                ], 500);
            }
        } catch (\Throwable $th) {
            //throw $th;
            return response()->json([
                'success' => false,
                'message' => 'Error updating group client. ' . $th->getMessage()
            ], 500);
        }
    }

    public function getGCContactPersons(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'gc_id' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid request data',
                'data' => $validator->errors()
            ], 400);
        }

        try {
            $gc_id = $request->input('gc_id');

            $result = $this->britam_db->table('contactpersoninfo')->select("*")->where("Client", $gc_id)->get();

            if ($result != null) {
                return response()->json([
                    'success' => true,
                    'message' => 'Contact persons fetched successfully',
                    'data' => $result
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'No contact persons found',
                    'data' => []
                ], 404);
            }
        } catch (\Throwable $th) {
            //throw $th;
            return response()->json([
                'success' => false,
                'message' => 'Error fetching contact persons' . $th->getMessage()
            ], 500);
        }
    }

    // create contact persons for a group client
    public function setGCContactPersons(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'gc_id' => 'required',
            'contact_persons' => 'required|array',
            'contact_persons.*.name' => 'required',
            'contact_persons.*.position' => 'required',
            'contact_persons.*.email' => 'required|email',
            'contact_persons.*.mobile' => 'required',
            'contact_persons.*.address' => 'nullable',
            'contact_persons.*.idType' => 'nullable',
            'contact_persons.*.idNumber' => 'nullable',
            'contact_persons.*.dob' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid request data',
                'data' => $validator->errors()
            ], 400);
        }

        try {
            $gc_id = $request->input('gc_id');
            $contact_persons = $request->input('contact_persons');

            foreach ($contact_persons as $contact_person) {
                $age = date_diff(date_create($contact_person['dob']), date_create('now'))->y;

                // make sure the email is unique across the entire contactpersoninfo table
                $email_exists = $this->britam_db->table('contactpersoninfo')->where('contact_email', $contact_person['email'])->exists();

                if ($email_exists) {
                    return response()->json([
                        'success' => false,
                        'message' => 'The email ' . $contact_person['email'] . ' already exists under another contact person'
                    ], 400);
                }

                // Insert contact person data
                $contact_person_id = $this->britam_db->table('contactpersoninfo')->insertGetId([
                    'Client' => $gc_id,
                    'contact' => $contact_person['name'],
                    'contact_position' => $contact_person['position'],
                    'contact_email' => $contact_person['email'],
                    'contact_telephone' => $contact_person['mobile'],
                    'contact_address' => $contact_person['address'] ?? null,
                    'IdType' => $contact_person['idType'] ?? null,
                    'IdNumber' => $contact_person['idNumber'] ?? null,
                    'Birthdate' => $contact_person['dob'] ?? null,
                    'Age' => $age,
                    'Status' => '001',
                    'created_by' => 'API',
                    'created_on' => now(),
                ]);

                // Insert portal user login info
                $portal_user_id = $this->britam_db->table('PortalUserLoginInfo')->insertGetId([
                    'ContactPerson' => $contact_person_id,
                ]);

                // client schemes
                $clientSchemes = $this->britam_db->table('polschemeinfo')->select('schemeID')->where('ClientNumber', $gc_id)->get();

                foreach ($clientSchemes as $clientScheme) {
                    // Check if the scheme is not already in ClientSchemesAccess
                    if (!$this->isSchemeInClientSchemesAccess($clientScheme, $portal_user_id)) {
                        // If not, add it
                        $this->addSchemeToClientSchemesAccess($clientScheme, $portal_user_id);
                    }
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Contact persons created successfully'
            ], 201);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating contact persons: ' . $th->getMessage()
            ], 500);
        }
    }
    //get 

    // get policies that the contact person has access to
    public function getGCContactPersonSchemes(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'contact_person_id' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid request data',
                'data' => $validator->errors()
            ], 400);
        }

        try {
            $contact_person_id = $request->input('contact_person_id');
            $is_pop_fund = $request->input('is_pop_fund');

            // get PortalUsageLoginInfo id from contact_person_id
            $user_id = $contact_person_id;
            $currentDate = date('Y-m-d');

            if (isset($is_pop_fund) && ($is_pop_fund == "1" || $is_pop_fund == 1)) {
                /*$schemes = $this->britam_db->table('polschemeinfo AS p')
                ->join('ClientSchemesAccess AS c', 'p.schemeID', '=', 'c.Scheme')
                ->join('glifeclass AS gc', 'p.class_code', '=', 'gc.class_code')
                ->select('p.*', \DB::raw("CASE 
                                    WHEN p.SchemeDescription IS NOT NULL THEN CONCAT(p.policy_no, ' - ', p.SchemeDescription)
                                    WHEN p.CompanyName IS NOT NULL THEN CONCAT(p.policy_no, ' - ', p.CompanyName)
                                    ELSE p.policy_no
                                END AS PolicyCompany"), 'gc.Description AS ProductDescription', 'gc.IsGroupLifeCover', 'gc.IsCreditLifeCover', 'gc.pen', 'gc.IsPopFund' )// ,'gc.IsLastExpense')
                ->where('p.class_code', 8)
                ->where(function ($query) {
                    $query->where('p.StatusCode', '001')
                        ->orWhere('p.StatusCode', '005');
                })
                ->where('c.AllowAccess', 1)
                ->get();*/
                $schemes = $this->britam_db->table('polschemeinfo AS p')
                    //->join('ClientSchemesAccess AS c', 'p.schemeID', '=', 'c.Scheme')
                    ->join('glifeclass AS gc', 'p.class_code', '=', 'gc.class_code')
                    ->select(
                        'p.*',
                        \DB::raw("CASE 
                                WHEN p.SchemeDescription IS NOT NULL THEN CONCAT(p.policy_no, ' - ', p.SchemeDescription)
                                WHEN p.CompanyName IS NOT NULL THEN CONCAT(p.policy_no, ' - ', p.CompanyName)
                                ELSE p.policy_no
                            END AS PolicyCompany"),
                        'gc.Description AS ProductDescription',
                        'gc.IsGroupLifeCover',
                        'gc.IsCreditLifeCover',
                        'gc.pen',
                        'gc.IsPopFund'
                    )
                    ->orWhere('p.class_code', 1)
                    ->orWhere('p.class_code', 5)
                    ->orWhere('p.class_code', 10)
                    ->orWhere('p.class_code', 8) // Replaced whereIn with orWhere
                    //->orWhere('p.class_code', 5)
                    //->orWhere('p.class_code', 1)
                    // ->orWhere(array(
                    //     'gc.pen' => 1,
                    //     'gc.IsPopFund' => 5,
                    //     'gc.class_code' => 1
                    // ))
                    //->where(function ($query) {
                    //$query->where('p.StatusCode', '001')
                    //->where('p.StatusCode', '001')
                    //->whereRaw('? BETWEEN p.DateFrom AND p.End_date', [$currentDate])// Current date between DateFrom and End_date
                    //})
                    //->where('c.AllowAccess', 1)
                    //->distinct() // This ensures you get distinct records
                    ->get();
            } else {
                //know if your are a client or a broker then querry separately...
                $loginObj = $this->britam_db->table('PortalUserLoginInfo')->where('Id', $user_id)->first();
                if($loginObj->IsBroker){
                    $brokerId = $loginObj->Broker;
                    //fetch broker schemes
                    $schemes = $this->britam_db->table('polschemeinfo AS p')
                    //->join('ClientSchemesAccess AS c', 'p.policy_no', '=', 'c.PolicyNumber')
                    ->join('glifeclass AS gc', 'p.class_code', '=', 'gc.class_code')
                    ->join('glifeclientinfo as q', 'p.ClientNumber', '=', 'q.Id')
                    ->select('p.*','q.name as CompanyName', \DB::raw("CASE 
                            WHEN p.SchemeDescription IS NOT NULL 
                            THEN CONCAT(p.policy_no, ' - ', p.SchemeDescription, ' (', FORMAT(p.DateFrom, 'dd/MM/yyyy'), ' - ', FORMAT(p.End_Date, 'dd/MM/yyyy'), ')')
                            WHEN q.name IS NOT NULL 
                            THEN CONCAT(p.policy_no, ' - ', q.name, ' (', FORMAT(p.DateFrom, 'dd/MM/yyyy'), ' - ', FORMAT(p.End_Date, 'dd/MM/yyyy'), ')')
                            ELSE CONCAT(p.policy_no, ' (', FORMAT(p.DateFrom, 'dd/MM/yyyy'), ' - ', FORMAT(p.End_Date, 'dd/MM/yyyy'), ')')
                        END AS PolicyCompany
                        "), 'gc.Description AS ProductDescription', 'gc.IsGroupLifeCover', 'gc.IsCreditLifeCover', 'gc.pen', 'gc.IsPopFund') // ,'gc.IsLastExpense')
                    ->where('p.interm_ID', $brokerId)
                    ->where(function ($query) {
                        $query->where('p.StatusCode', '001')
                            ->orWhere('p.StatusCode', '005')
                            ->orWhere('p.StatusCode', '011');
                    })->distinct()
                    //->where('c.AllowAccess', 1)
                    //->whereRaw('? BETWEEN p.DateFrom AND p.End_date', [$currentDate])
                    ->distinct()
                    ->get();

                }else{
                    $clientId = $loginObj->GroupClient;
                    //fetch client schemes
                    $schemes = $this->britam_db->table('polschemeinfo AS p')
                    //->join('ClientSchemesAccess AS c', 'p.policy_no', '=', 'c.PolicyNumber')
                    ->join('glifeclass AS gc', 'p.class_code', '=', 'gc.class_code')
                    ->join('glifeclientinfo as q', 'p.ClientNumber', '=', 'q.Id')
                    ->select('p.*','q.name as CompanyName', \DB::raw("CASE 
                            WHEN p.SchemeDescription IS NOT NULL 
                            THEN CONCAT(p.policy_no, ' - ', p.SchemeDescription, ' (', FORMAT(p.DateFrom, 'dd/MM/yyyy'), ' - ', FORMAT(p.End_Date, 'dd/MM/yyyy'), ')')
                            WHEN q.name IS NOT NULL 
                            THEN CONCAT(p.policy_no, ' - ', q.name, ' (', FORMAT(p.DateFrom, 'dd/MM/yyyy'), ' - ', FORMAT(p.End_Date, 'dd/MM/yyyy'), ')')
                            ELSE CONCAT(p.policy_no, ' (', FORMAT(p.DateFrom, 'dd/MM/yyyy'), ' - ', FORMAT(p.End_Date, 'dd/MM/yyyy'), ')')
                        END AS PolicyCompany
                        "), 'gc.Description AS ProductDescription', 'gc.IsGroupLifeCover', 'gc.IsCreditLifeCover', 'gc.pen', 'gc.IsPopFund') // ,'gc.IsLastExpense')
                    //->where('c.PortalUser', $user_id)
                    ->where('p.ClientNumber', $clientId)
                    ->where(function ($query) {
                        $query->where('p.StatusCode', '001')
                            ->orWhere('p.StatusCode', '005')
                            ->orWhere('p.StatusCode', '011')
                            ->orWhere('p.StatusCode', '010');
                    })->distinct()
                    //->where('c.AllowAccess', 1)
                    //->whereRaw('? BETWEEN p.DateFrom AND p.End_date', [$currentDate])
                    //->distinct()
                    //->whereRaw('? BETWEEN p.DateFrom AND p.End_date', [$currentDate])
                    ->distinct()
                    ->get();
                }

                
            }



            if ($schemes->isNotEmpty()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Schemes fetched successfully',
                    'data' => $schemes
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'No schemes found',
                    'data' => []
                ], 404);
            }
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching schemes: ' . $th->getMessage()
            ], 500);
        }
    }

    public function updateGCContactPersons(Request $request) {}

    public function getGCDirectors(Request $request)
    {
        //set validator
        $validator = Validator::make($request->all(), [
            'gc_id' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid request data',
                'data' => $validator->errors()
            ], 400);
        }

        try {
            $gc_id = $request->input('gc_id');

            $result = $this->britam_db->table('directorsinfo')->select("*")->where("Client", $gc_id)->get();

            if ($result != null) {
                return response()->json([
                    'success' => true,
                    'message' => 'Directors fetched successfully',
                    'data' => $result
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'No directors found',
                    'data' => []
                ], 404);
            }
        } catch (\Throwable $th) {
            //throw $th;
            return response()->json([
                'success' => false,
                'message' => 'Error fetching directors' . $th->getMessage()
            ], 500);
        }
    }
    public function setGCDirectors(Request $request) {}

    public function updateGCDirectors(Request $request) {}

    public function getGCBankDetails(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'gc_id' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid request data',
                'data' => $validator->errors()
            ], 400);
        }

        try {

            $gc_id = $request->input('gc_id');

            $results = $this->britam_db->table('intermediarybanksinfo')->select("*")->where("Client", $gc_id)->get();

            if ($results != null) {
                return response()->json([
                    'success' => true,
                    'message' => 'Bank details fetched successfully',
                    'data' => $results
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'No bank details found',
                    'data' => []
                ], 404);
            }
        } catch (\Throwable $th) {
            //throw $th;
            return response()->json([
                'success' => false,
                'message' => 'Error fetching bank details' . $th->getMessage()
            ], 500);
        }
    }

    public function setGCBankDetails(Request $request) {}

    public function updateGCBankDetails(Request $request) {}
    //get debit notes of a particular scheme
    public function getDebitNotes(Request $request)
    {
        try {

            $validations = [
                'contact_person_id' => 'required',
                'is_group_client' => 'boolean|required'
            ];

            $validator = Validator::make($request->all(), $validations);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid request data',
                    'data' => $validator->errors()
                ], 400);
            }

            $contact_person_id = $request->input('contact_person_id');
            $is_group_client = $request->input('is_group_client');
            $results = null;

            if ($contact_person_id != null && $is_group_client == 1) {

                $contact_person = $this->britam_db->table('PortalUserLoginInfo')->select('ContactPerson')->where('Id', $contact_person_id)->first();

                $client = $this->britam_db->table('contactpersoninfo')->select('Client')->where('id', $contact_person->ContactPerson)->first();

                $results = $this->britam_db->table('debitmastinfo AS d')
                    ->join('polschemeinfo AS p', 'd.schemeNo', '=', 'p.schemeID')
                    ->leftjoin('glsuppinfo AS g', 'd.endorsement', '=', 'g.id')
                    ->select("d.*", \DB::raw("CASE 
                            WHEN p.SchemeDescription IS NOT NULL THEN CONCAT(p.SchemeDescription, ' - ', CAST(p.policy_no AS VARCHAR(255)))
                            WHEN p.CompanyName IS NOT NULL THEN CONCAT(p.CompanyName, ' - ', CAST(p.policy_no AS VARCHAR(255)))
                            ELSE CAST(p.policy_no AS VARCHAR(255))
                        END AS PolicyCompany"), "g.Narration AS EndorsementNarration")
                    ->where("d.client_no", $client->Client)
                    ->where("d.drcr", "DR")
                    ->get();
            } else if ($contact_person_id != null && $is_group_client == 0) {

                // print the db connection
                $contact_person = $this->britam_db->table('PortalUserLoginInfo')->select('ContactPerson')->where('Id', $contact_person_id)->first();


                $intermediary = $this->britam_db->table('contactpersoninfo')->select('Intermediary')->where('id', $contact_person->ContactPerson)->first();

                $results = $this->britam_db->table('debitmastinfo AS d')
                    ->join('polschemeinfo AS p', 'd.schemeNo', '=', 'p.schemeID')
                    ->leftjoin('glsuppinfo AS g', 'd.endorsement', '=', 'g.id')
                    ->select("d.*", \DB::raw("CASE 
                            WHEN p.SchemeDescription IS NOT NULL THEN CONCAT(p.SchemeDescription, ' - ', CAST(p.policy_no AS VARCHAR(255)))
                            WHEN p.CompanyName IS NOT NULL THEN CONCAT(p.CompanyName, ' - ', CAST(p.policy_no AS VARCHAR(255)))
                            ELSE CAST(p.policy_no AS VARCHAR(255))
                        END AS PolicyCompany"), "g.Narration AS EndorsementNarration")
                    ->where("d.interm_ID", $intermediary->Intermediary)
                    ->where("d.drcr", "DR")
                    ->where("d.isReversed", 0)
                    ->get();
            } else {
                //thow an error
                return response()->json([
                    'success' => false,
                    'message' => 'Something went wrong. Please try again.',
                    'data' => []
                ], 400);
            }





            if (sizeof($results) > 0) {
                return response()->json([
                    'success' => true,
                    'message' => 'Debit notes fetched successfully',
                    'count' => count($results),
                    'data' => $results
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'No debit notes found',
                    'data' => []
                ], 404);
            }
        } catch (\Throwable $th) {
            //throw $th;
            return response()->json([
                'success' => false,
                'message' => 'Error fetching debit notes' . $th->getMessage()
            ], 500);
        }
    }

    //get credit notes of a particular scheme
    public function getCreditNotes(Request $request)
    {
        try {

            $validations = [
                'contact_person_id' => 'required',
                'is_group_client' => 'boolean|required'
            ];

            $validator = Validator::make($request->all(), $validations);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid request data',
                    'data' => $validator->errors()
                ], 400);
            }

            $contact_person_id = $request->input('contact_person_id');
            $is_group_client = $request->input('is_group_client');

            $results = null;

            if ($contact_person_id != null && $is_group_client == 1) {

                $contact_person = $this->britam_db->table('PortalUserLoginInfo')->select('ContactPerson')->where('Id', $contact_person_id)->first();

                $client = $this->britam_db->table('contactpersoninfo')->select('Client')->where('id', $contact_person->ContactPerson)->first();

                $results = $this->britam_db->table('debitmastinfo AS d')
                    ->join('polschemeinfo AS p', 'd.schemeNo', '=', 'p.schemeID')
                    ->leftjoin('glsuppinfo AS g', 'd.endorsement', '=', 'g.id')
                    ->select("d.*", \DB::raw("CASE 
                            WHEN p.SchemeDescription IS NOT NULL THEN CONCAT(p.SchemeDescription, ' - ', CAST(p.policy_no AS VARCHAR(255)))
                            WHEN p.CompanyName IS NOT NULL THEN CONCAT(p.CompanyName, ' - ', CAST(p.policy_no AS VARCHAR(255)))
                            ELSE CAST(p.policy_no AS VARCHAR(255))
                        END AS PolicyCompany"), "g.Narration AS EndorsementNarration")
                    ->where("d.client_no", $client->Client)
                    ->where("d.drcr", "CR")
                    ->get();
            } else if ($contact_person_id != null && $is_group_client == 0) {

                $contact_person = $this->britam_db->table('PortalUserLoginInfo')->select('ContactPerson')->where('Id', $contact_person_id)->first();

                $intermediary = $this->britam_db->table('contactpersoninfo')->select('Intermediary')->where('id', $contact_person->ContactPerson)->first();

                $results = $this->britam_db->table('debitmastinfo AS d')
                    ->join('polschemeinfo AS p', 'd.schemeNo', '=', 'p.schemeID')
                    ->leftjoin('glsuppinfo AS g', 'd.endorsement', '=', 'g.id')
                    ->select("d.*", \DB::raw("CASE 
                            WHEN p.SchemeDescription IS NOT NULL THEN CONCAT(p.SchemeDescription, ' - ', CAST(p.policy_no AS VARCHAR(255)))
                            WHEN p.CompanyName IS NOT NULL THEN CONCAT(p.CompanyName, ' - ', CAST(p.policy_no AS VARCHAR(255)))
                            ELSE CAST(p.policy_no AS VARCHAR(255))
                        END AS PolicyCompany"), "g.Narration AS EndorsementNarration")
                    ->where("d.interm_ID", $intermediary->Intermediary)
                    ->where("d.drcr", "CR")
                    ->get();
            } else {
                //thow an error
                return response()->json([
                    'success' => false,
                    'message' => 'Something went wrong. Please try again.',
                    'data' => []
                ], 400);
            }

            if ($results != null) {
                return response()->json([
                    'success' => true,
                    'message' => 'Credit notes fetched successfully',
                    'count' => count($results),
                    'data' => $results
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'No credit notes found',
                    'data' => []
                ], 404);
            }
        } catch (\Throwable $th) {
            //throw $th;
            return response()->json([
                'success' => false,
                'message' => 'Error fetching credit notes' . $th->getMessage()
            ], 500);
        }
    }
}
