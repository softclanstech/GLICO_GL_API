<?php

namespace App\Http\Controllers\V1\Portal\GroupClient;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\V2\Portal\GroupClient\EmailController2;

class SchemesController extends Controller
{
    //

    private function sendEndorsementRequestNotification($request_id, $scheme_id, $endorsement_type, $effective_date, $requested_change, $token, $email_address, $contact_person_email_who_initiated)
    {
        $servicing_team_email = env('SERVICING_TEAM_EMAIL', 'rpinochio@britam.com');
        $scheme_name = $this->britam_db->table('polschemeinfo')->select('SchemeDescription')->where('SchemeID', $scheme_id)->first()->SchemeDescription;
        $policy_number = $this->britam_db->table('polschemeinfo')->select('policy_no')->where('SchemeID', $scheme_id)->first()->policy_no;

        // name and email of the contact person who initiated the request
        $contact_person_name = $this->britam_db->table('contactpersoninfo')->select('contact')->where('contact_email', $contact_person_email_who_initiated)->first()->contact;

        // if the emails are for contact persons
        $contact_person_customer_name = $this->britam_db->table('contactpersoninfo')->select('contact')->where('Email', $email_address)->first()->contact;

        if ($endorsement_type == 2) {
            $subject = "New Joiner Endorsement Request Notification";
            $message_to_be_sent = "Dear $contact_person_customer_name,\n\n" .
                "Thank you for insuring with us.\n\n" .
                "This is to confirm that we have received your new joiner request with the below details;\n\n" .
                "Date: $effective_date\n\n" .
                "Scheme: $scheme_name\n\n" .
                "Policy Number: $policy_number\n\n" .
                // show who and the email of the person that initiated
                "Initiated by: $contact_person_name\n\n" .
                "A notification will be sent to your email once the endorsement has been fully processed.\n\n" .
                "Should you have any queries or concerns regarding your policy, kindly reach out to grouplifeservices@britam.com or call us on 0703 094 000.
                ";
        } else if ($endorsement_type == 3) {
            $subject = "Leaver Endorsement Request Notification";
            $message_to_be_sent = "Dear $contact_person_customer_name,\n\n" .
                "Thank you for insuring with us.\n\n" .
                "This is to confirm that we have received your leaver request with the below details;\n\n" .
                "Date: $effective_date\n\n" .
                "Scheme: $scheme_name\n\n" .
                "Policy Number: $policy_number\n\n" .
                "Initiated by: $contact_person_name\n\n" .
                "A notification will be sent to your email once the endorsement has been fully processed.\n\n" .
                "Should you have any queries or concerns regarding your policy, kindly reach out to grouplifeservices@britam.com or call us on 0703 094 000.
                ";
        } else if ($endorsement_type == 6) {
            $subject = "Salary Revision Endorsement Request Notification";
            $message_to_be_sent = "Dear $contact_person_customer_name,\n\n" .
                "Thank you for insuring with us.\n\n" .
                "This is to confirm that we have received your salary revision request with the below details;\n\n" .
                "Date: $effective_date\n\n" .
                "Scheme: $scheme_name\n\n" .
                "Policy Number: $policy_number\n\n" .
                "Initiated by: $contact_person_name\n\n" .
                "A notification will be sent to your email once the endorsement has been fully processed.\n\n" .
                "Should you have any queries or concerns regarding your policy, kindly reach out to grouplifeservices@britam.com or call us on 0703 094 000.
                ";
        } else if ($endorsement_type == 7) {
            $subject = "Dependant Addition Endorsement Request Notification";
            $message_to_be_sent = "Dear $contact_person_customer_name,\n\n" .
                "Thank you for insuring with us.\n\n" .
                "This is to confirm that we have received your dependant addition request with the below details;\n\n" .
                "Date: $effective_date\n\n" .
                "Scheme: $scheme_name\n\n" .
                "Policy Number: $policy_number\n\n" .
                "Initiated by: $contact_person_name\n\n" .
                "A notification will be sent to your email once the endorsement has been fully processed.\n\n" .
                "Should you have any queries or concerns regarding your policy, kindly reach out to grouplifeservices@britam.com or call us on 0703 094 000.
                ";
        }
        // Send the email
        self::britam_email_sending($subject, $message_to_be_sent, $email_address, $token);
    }


    private function calculateAge($date_of_birth)
    {
        $dob = new \DateTime($date_of_birth);
        $now = new \DateTime();
        $difference = $now->diff($dob);
        $age = $difference->y;
        return $age;
    }

    // scheme categories
    public function getSchemeCategories(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'scheme_id' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid request data',
                'data' => $validator->errors()
            ], 400);
        }

        try {
            $results = $this->britam_db->table('glifecateg')->select('*')
                ->where('SchemeId', $request->input('scheme_id'))
                ->get();

            if ($results != null) {
                return response()->json([
                    'success' => true,
                    'message' => 'Scheme categories fetched successfully',
                    'data' => $results
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'No categories found'
                ], 404);
            }
        } catch (\Throwable $th) {
            //throw $th;
            return response()->json([
                'success' => false,
                'message' => 'Error fetching categories' . $th->getMessage()
            ], 500);
        }
    }

    public function getClientSchemes(Request $request)
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

            $client_number = $this->britam_db->table('glifeclientinfo')->select('client_number', 'client_type')->where('Id', $gc_id)->first();

            $client_type = $client_number->client_type;

            if ($client_type == 1) {

                //concatenate the p.policy_no to the p.CompanyName on th below query

                //$results = $this->britam_db->table('polschemeinfo As p')->select("p.*")->where("ClientNumber", $gc_id)->get();
                // if CompanyName is null then concatenate with SchemeDescription, if both null then just give policy_no
                $results = $this->britam_db->table('polschemeinfo AS p')
                    ->select(
                        '*',
                        \DB::raw("CASE 
                            WHEN p.SchemeDescription IS NOT NULL THEN CONCAT(p.policy_no, ' - ', p.SchemeDescription)
                            WHEN p.CompanyName IS NOT NULL THEN CONCAT(p.policy_no, ' - ', p.CompanyName)
                            ELSE p.policy_no
                        END AS PolicyCompany")
                    )
                    ->where('ClientNumber', $gc_id)
                    ->get();




                if ($results != null) {
                    return response()->json([
                        'success' => true,
                        'message' => 'Policy schemes fetched successfully',
                        'data' => $results
                    ], 200);
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'No schemes found'
                    ], 404);
                }
            } else {

                return response()->json([
                    'success' => false,
                    'message' => 'Client is not a a corporate institution.'
                ], 400);
            }
        } catch (\Throwable $th) {
            //throw $th;
            return response()->json([
                'success' => false,
                'message' => 'Error fetching schemes' . $th->getMessage()
            ], 500);
        }
    }

    public function getMembersPerScheme(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'scheme_id' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid request data',
                'data' => $validator->errors()
            ], 400);
        }

        try {

            $scheme_id = $request->input('scheme_id');

            $client_type = $this->britam_db->table('glifeclientinfo', 'g')
                ->join('polschemeinfo as p', 'p.ClientNumber', '=', 'g.Id')
                //->join('glmembersinfo as gm', 'gm.SchemeID', '=', 'p.schemeID')
                ->where('p.SchemeID', $scheme_id)
                ->select('g.client_type')
                ->first();

            if ($client_type == null) {
                return response()->json([
                    'success' => false,
                    'message' => 'No results found.',
                    'data' => []
                ], 400);
                // }

                // if ($client_type->client_type != 1) {
                //     return response()->json([
                //         'success' => false,
                //         'message' => 'Scheme is not for a corporate institution.'
                //     ], 400);
            } else {
                $results = $this->britam_db->table('glmembersinfo')->select("*")->where("schemeID", $scheme_id)->get();

                if (sizeof($results) > 0) {
                    return response()->json([
                        'success' => true,
                        'message' => 'Scheme members fetched successfully',
                        'count' => count($results),
                        'data' => $results

                    ], 200);
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'No members found'
                    ], 404);
                }
            }
        } catch (\Throwable $th) {
            //throw $th;
            return response()->json([
                'success' => false,
                'message' => 'Error fetching schemes' . $th->getMessage()
            ], 500);
        }
    }

    //search member by name

    public function searchMemberBySurname(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'scheme_id' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid request data',
                'data' => $validator->errors()
            ], 400);
        }

        try {
            $search_name = $request->name;
            $scheme_id = $request->scheme_id;

            //confirm if the scheme_id has members

            $client_type = $this->britam_db->table('glifeclientinfo', 'g')
                ->join('polschemeinfo as p', 'p.ClientNumber', '=', 'g.Id')
                //->join('glmembersinfo as gm', 'gm.SchemeID', '=', 'p.schemeID')
                ->where('p.schemeID', $scheme_id)
                ->select('g.client_type')
                ->first();

            if ($client_type == null) {
                return response()->json([
                    'success' => false,
                    'message' => 'No results found.',
                    'data' => []
                ], 400);
                // }

                // if ($client_type->client_type != 1) {
                //     return response()->json([
                //         'success' => false,
                //         'message' => 'Scheme is not for a corporate institution.'
                //     ], 400);
            } else {

                //TODO - check if its popfund....
                //1. Check if class_code is popfund
                $StatementObj = $this->britam_db->table('polschemeinfo', 'g')
                ->join('glifeclass as p', 'p.class_code', '=', 'g.class_code')
                //->join('glmembersinfo as gm', 'gm.SchemeID', '=', 'p.schemeID')
                ->where('g.schemeID', $scheme_id)
                ->select('p.IsPopFund','p.pen','p.IsGrp')
                ->first();

                $IsPopFund = $StatementObj->IsPopFund;
                $Ispen = $StatementObj->pen;
                $IsGrp = $StatementObj->IsGrp;

                if($IsPopFund == "1" || $IsPopFund == 1 || $Ispen == "1" || $Ispen == 1 || $IsGrp == "1" || $IsGrp == 1){
                    if($IsPopFund == "1" || $IsPopFund == 1 || $Ispen == "1" || $Ispen == 1){
                        $results = $this->britam_db->table('glmembersinfo')
                        ->select("*")
                        ->where("schemeID", $scheme_id)
                        ->where(function ($query) use ($search_name) {
                            $query->where("member_no", '=', $search_name)
                                ->where("IsActive", '=', "1");
                        })
                        ->get();
                    } else if(($Ispen == "1" || $Ispen == 1) && ($IsGrp == "1" || $IsGrp == 1)){
                        
                        $results = $this->britam_db->table('polschemeinfo')
                        ->select("CompanyName as Names", "policy_no as member_no", "schemeID as SchemeID")
                        ->where("schemeID", $scheme_id)
                        ->get();
                    }
                    
                } else {
                    $results = $this->britam_db->table('glmembersinfo')
                    ->select("*")
                    ->where("schemeID", $scheme_id)
                    ->where(function ($query) use ($search_name) {
                        $query->where("Names", 'LIKE', '%' . $search_name . '%')
                            ->orWhere("member_no", 'LIKE', '%' . $search_name . '%');
                    })
                    ->get(); 
                }

                if (sizeof($results) > 0) {

                    return response()->json([
                        'success' => true,
                        'message' => 'Scheme members fetched successfully',
                        'count' => count($results),
                        'data' => $results

                    ], 200);
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'No members found'
                    ], 404);
                }
            }
        } catch (\Throwable $th) {
            //throw $th;
            return response()->json([
                'success' => false,
                'message' => 'Error fetching data' . $th->getMessage()
            ], 500);
        }
    }

    //search member by name and loan number for the loan scheme by innejoining GlifeMemberLoans table and glmembersinfo
    public function search_by_name_and_loan_number(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'scheme_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid request data',
                'data' => $validator->errors()
            ], 400);
        }

        try {
            $search_name = $request->name; // either be loan number, name or member number
            $scheme_id = $request->scheme_id;

            //confirm if the scheme_id has members

            $results = $this->britam_db->table('glmembersinfo')
                ->join('glifeMemberLoans', 'glifeMemberLoans.MemberID', '=', 'glmembersinfo.MemberId')
                ->select("glmembersinfo.*", "glifeMemberLoans.*")
                ->where("glmembersinfo.schemeID", $scheme_id)
                ->where(function ($query) use ($search_name) {
                    $query->where("glmembersinfo.Names", 'LIKE', '%' . $search_name . '%')
                        ->orWhere("glmembersinfo.member_no", 'LIKE', '%' . $search_name . '%')
                        ->orWhere("glifeMemberLoans.LoanNumber", 'LIKE', '%' . $search_name . '%');
                })
                ->get();

            if (sizeof($results) > 0) {

                return response()->json([
                    'success' => true,
                    'message' => 'Scheme members fetched successfully',
                    'count' => count($results),
                    'data' => $results

                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'No members found'
                ], 404);
            }
        } catch (\Throwable $th) {
            //throw $th;
            return response()->json([
                'success' => false,
                'message' => 'Error fetching data' . $th->getMessage()
            ], 500);
        }
    }

    public function setEndorsementRequest(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'scheme_id' => 'required|integer',
            'endorsement_type' => 'required|integer',
            'effective_date' => 'required|date',
            'requested_change' => 'string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid request data',
                'data' => $validator->errors()
            ], 400);
        }

        try {
            $scheme_id = $request->input('scheme_id');
            $endorsement_type = $request->input('endorsement_type');
            $effective_date = $request->input('effective_date');
            $requested_change = $request->input('requested_change');


            //check if it a corporate institution
            $client_type = $this->britam_db->table('glifeclientinfo', 'g')
                ->join('polschemeinfo as p', 'p.ClientNumber', '=', 'g.Id')
                //->join('glmembersinfo as gm', 'gm.SchemeID', '=', 'p.schemeID')
                ->where('p.SchemeID', $scheme_id)
                ->select('g.client_type')
                ->first();

            if ($client_type == null) {
                return response()->json([
                    'success' => false,
                    'message' => 'No results found.',
                    'data' => []
                ], 400);
                // } else {
                //     if ($client_type->client_type != 1) {
                //         return response()->json([
                //             'success' => false,
                //             'message' => 'Scheme is not for a corporate institution.'
                //         ], 400);
            } else {
                //check if the scheme has a pending request
                $pending_request = $this->britam_db->table('EndorsementRequest')->select("*")->where("PolicyNo", $scheme_id)->where("Status", 1)->first();

                if ($pending_request != null) {
                    return response()->json([
                        'success' => false,
                        'message' => 'There is a pending request for this scheme.'
                    ], 400);
                } else {
                    //create a new request
                    $request_id = $this->britam_db->table('EndorsementRequest')->insertGetId([
                        'PolicyNo' => $scheme_id,
                        'EndorsementType' => $endorsement_type,
                        'EffectiveDate' => $effective_date,
                        'RequestedChanges' => $requested_change,
                        'IsBulkUpload' => 0,
                        'Status' => 1,
                        'created_on' => date('Y-m-d H:i:s'),
                        'created_by' => 'API'
                    ]);

                    if ($request_id > 0) {
                        return response()->json([
                            'success' => true,
                            'message' => 'Request created successfully',
                            'data' => [
                                'request_id' => $request_id
                            ]
                        ], 200);
                    } else {
                        return response()->json([
                            'success' => false,
                            'message' => 'Error creating request'
                        ], 500);
                    }
                }
            }
        } catch (\Throwable $th) {
            //throw $th;
            return response()->json([
                'success' => false,
                'message' => 'Error creating request' . $th->getMessage()
            ], 500);
        }
    }

    public function setEndorsementMembers(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'scheme_id' => 'required|integer',
            'EndorsementRequest' => 'required|integer',
            'member' => 'required|array',
            'member.*.endorsement_type' => 'required|integer',
            'member.*.effective_date' => 'required|date',
            'member.*.requested_change' => 'string',
            'member.*.MemberName' => 'string',
            'member.*.DateOfBirth' => 'date',
            'member.*.MemberNo' => 'string',
            'member.*.MemberSalary' => 'string',
            'member.*.IdType' => 'string',
            'member.*.IdNumber' => 'string',
            'member.*.HasDependants' => 'integer',

        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid request data',
                'data' => $validator->errors()
            ], 400);
        }

        try {
            $membersData = [];
            foreach ($request['members'] as $member) {
                $membersData[] = [
                    'EndorsementRequest' => $request->input('EndorsementRequest'),
                    'EndorsementType' => $member['endorsement_type'],
                    'EffectiveDate' => $member['effective_date'],
                    'RequestedChanges' => $member['requested_change'],
                    'MemberName' => $member['MemberName'],
                    'DateOfBirth' => $member['DateOfBirth'],
                    'MemberNo' => $member['MemberNo'],
                    'MemberSalary' => $member['MemberSalary'],
                    'IdType' => $member['IdType'],
                    'IdNumber' => $member['IdNumber'],
                    'HasDependants' => $member['HasDependants'],
                    'created_on' => date('Y-m-d H:i:s'),
                    'created_by' => 'API'
                ];
            }

            $results = $this->britam_db->table('EndorsementMembers')->insert($membersData);

            if ($results > 0) {
                return response()->json([
                    'success' => true,
                    'message' => 'Members added successfully'
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Error adding members'
                ], 500);
            }
        } catch (\Throwable $th) {
            //throw $th;
            return response()->json([
                'success' => false,
                'message' => 'Error adding members' . $th->getMessage()
            ], 500);
        }
    }

    //endorsementRequests
    public function endorsementRequest(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'scheme_id' => 'required|integer',
            'endorsement_type' => 'required|integer',
            'effective_date' => 'required|date',
            'portal_user_id' => 'required', // user making the request
            'policy_member_id' => 'string',
            'requested_change' => 'required|string',
            'MemberName' => 'string',
            'DateOfBirth' => 'date',
            'MemberNo' => 'string',
            'MemberSalary' => 'nullable',
            'MemberEmail' => 'nullable',
            'DependantEmail' => 'nullable',
            'IdType' => 'string',
            'IdNumber' => 'string',
            'Exitday' => 'date',
            'HasDependants' => 'integer',
            'dependants' => 'array',
            'dependants.*.DependantFullName' => 'string',
            'dependants.*.DependantDOB' => 'date',
            'dependants.*.RelationshipType' => 'string',
            'dependants.*.DependantJoiningDate' => 'date',
            'files' => 'array',
            'files.*' => 'file|mimes:pdf,jpg,jpeg,png,xls,xlsx,tiff|max:21474836480',
            'category_code' => 'nullable|string',
            'premium_rate' => 'nullable|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid request data',
                'data' => $validator->errors()
            ], 400);
        }

        try {
            $scheme_id = $request->input('scheme_id');
            $endorsement_type = $request->input('endorsement_type');
            $effective_date = $request->input('effective_date');
            $requested_change = $request->input('requested_change');
            $member_name = $request->input('MemberName');
            $date_of_birth = $request->input('DateOfBirth');
            //calculate age
            $member_age = $this->calculateAge($date_of_birth);
            $member_no = $request->input('MemberNo');
            $new_member_salary = (float) $request->input('MemberSalary');
            $id_type = $request->input('IdType');
            $id_number = $request->input('IdNumber');
            $has_dependants = $request->input('HasDependants');
            $policy_member_id = $request->input('policy_member_id');
            $exit_day = $request->input('Exitday');
            $login_user_id = $request->input('portal_user_id');
            $member_email = $request->input('MemberEmail');
            //$dependant_email = $request->input('DependantEmail');
            $category_code = $request->input('category_code');
            $premium_rate = $request->input('premium_rate');

            // Variables to keep for different endorsement types
            // 3 is for exits
            // 2 for joiners
            // 6 for salary change
            // 7 adding just dependants to existing members
            $variables_to_keep = [
                3 => ['scheme_id', 'endorsement_type', 'effective_date', 'requested_change', 'policy_member_id', 'exit_day', 'files', 'files.*', 'login_user_id', 'category_code', 'premium_rate'],
                2 => ['scheme_id', 'endorsement_type', 'effective_date', 'requested_change', 'member_name', 'date_of_birth', 'member_age', 'login_user_id', 'member_no', 'member_salary', 'member_email', 'dependant_email', 'id_type', 'id_number', 'has_dependants', 'dependants', 'dependants.*.DependantFullName', 'dependants.*.DependantDOB', 'dependants.*.RelationshipType', 'dependants.*.DependantJoiningDate', 'files', 'files.*', 'category_code', 'premium_rate'],
                6 => ['scheme_id', 'endorsement_type', 'effective_date', 'requested_change', 'policy_member_id', 'new_member_salary', 'files', 'files.*', 'login_user_id', 'category_code', 'premium_rate'],
                7 => ['scheme_id', 'endorsement_type', 'effective_date', 'requested_change', 'policy_member_id', 'member_email', 'dependant_email', 'dependants', 'dependants.*.DependantFullName', 'dependants.*.DependantDOB', 'dependants.*.RelationshipType', 'dependants.*.DependantJoiningDate', 'files', 'files.*', 'login_user_id', 'category_code', 'premium_rate']
            ];

            // Check if the endorsement type exists in the array
            if (isset($variables_to_keep[$endorsement_type])) {
                // Extract the array of variable names associated with the endorsement type
                $variables_to_keep_current = $variables_to_keep[$endorsement_type];

                // Loop through each variable name
                foreach ($variables_to_keep_current as $var_name) {
                    // If the variable is not set, set it to null
                    if (!isset($$var_name)) {
                        ${$var_name} = null;
                    }
                }
            }

            //begin transaction
            $this->britam_db->beginTransaction();

            // get contact person id using login_user_id
            $contact_person_id = $this->britam_db->table('PortalUserLoginInfo')->select('ContactPerson')->where('Id', $login_user_id)->first();

            if ($policy_member_id != null) {
                //check if the member exists
                $specificMember = $this->britam_db->table('glmembersinfo')->select("*")->where("MemberId", $policy_member_id)->where("SchemeID", $scheme_id)->first();

                if ($specificMember == null) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Member does not exist.'
                    ], 400);
                }

                // check if member is active
                if ($specificMember->IsActive != 1) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Member is not active.'
                    ], 400);
                }

                //Log::channel('corporate_api')->info('Member to be removed: ' . json_encode($specificMember, JSON_PRETTY_PRINT));
            }

            $membersUnderScheme = $this->britam_db->table('glmembersinfo')->select("*")->where("schemeID", $scheme_id)->get();

            if (sizeof($membersUnderScheme) > 0) {
                //create a new request
                $dependants_only_addition = 0;
                $exits_endorse_type = null;
                $joiner_endorse_type = null;
                $salary_rev_endorse_type = null;
                $joiner_dep_endorse_type = null;

                if ($endorsement_type == 2) {

                    $joiner_endorse_type = $this->britam_db->table('glifeEndorsementType')->select("id")->where("IsSupplimentary", 1)->first();
                } else if ($endorsement_type == 3) {

                    $exits_endorse_type = $this->britam_db->table('glifeEndorsementType')->select("id")->where("IsDeletion", 1)->first();
                } else if ($endorsement_type == 6) {

                    $salary_rev_endorse_type = $this->britam_db->table('glifeEndorsementType')->select("id")->where("IsRevisedSalary", 1)->first();
                } else if ($endorsement_type == 7) {

                    $joiner_dep_endorse_type = $this->britam_db->table('glifeEndorsementType')->select("id")->where("IsSupplimentary", 1)->first();
                    //Log::channel('corporate_api')->info('Joiner Dependant Endorsement type: ' . json_encode($joiner_dep_endorse_type, JSON_PRETTY_PRINT));
                    $dependants_only_addition = 1;
                }

                //Log::channel('corporate_api')->info('Endorsement type: ' . json_encode($endorsement_type_id, JSON_PRETTY_PRINT));

                $endorsement_type_id = null;

                if ($exits_endorse_type != null) {
                    $endorsement_type_id = $exits_endorse_type;
                } else if ($joiner_endorse_type != null) {
                    $endorsement_type_id = $joiner_endorse_type;
                } else if ($salary_rev_endorse_type != null) {
                    $endorsement_type_id = $salary_rev_endorse_type;
                } else if ($joiner_dep_endorse_type != null) {
                    $endorsement_type_id = $joiner_dep_endorse_type;
                }

                $endorsement_type = $endorsement_type_id->id;

                $request_number = $this->generateRequestNumber(true, false, $scheme_id);

                $request_id = $this->britam_db->table('EndorsementRequest')->insertGetId([
                    'EndorsementRequestNumber' => $request_number,
                    'PolicyNo' => $scheme_id,
                    'ContactPerson' => $contact_person_id->ContactPerson,
                    'DependantsAddition' => $dependants_only_addition,
                    'EndorsementType' => $endorsement_type,
                    'EffectiveDate' => $effective_date,
                    'RequestedChanges' => $requested_change,
                    'Status' => 1,
                    'created_on' => date('Y-m-d H:i:s'),
                    'created_by' => 'API'
                ]);

                // update glmembersinfo table to add request id to it
                if ($exits_endorse_type != null) { // 3 being leavers

                    $member_id_present = $this->britam_db->table('EndorsementMembers')->select("*")->where("MemberId", $policy_member_id)->first();

                    if ($member_id_present != null) {

                        // should check if the endorsement request tied to the member was already processed

                        $endorsement_request = $member_id_present->EndorsementRequest;

                        $endorse_status_processed = $this->britam_db->table('EndorsementRequestStatus')->where('IsProcessed', 1)->first();

                        // check the $endorsement_request against EndorsementRequest table and see if it has been processed
                        $endorsement_request_status = $this->britam_db->table('EndorsementRequest')->select("*")->where("Id", $endorsement_request)->where("Status", $endorse_status_processed->Id)->first();

                        if ($endorsement_request_status == null) {
                            return response()->json([
                                'success' => false,
                                'message' => 'Member already exists in an existing pending endorsement request'
                            ], 400);
                        }
                    }

                    $this->britam_db->table('glmembersinfo')->where('MemberId', $policy_member_id)->update(['EndRequest' => $request_id]);

                    $updatedMemberDetails = $this->britam_db->table('glmembersinfo')->select("*")->where("MemberId", $policy_member_id)->where("SchemeID", $scheme_id)->where("EndRequest", $request_id)->first();

                    // check if member exits

                    if ($updatedMemberDetails == null) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Member does not exist.'
                        ], 400);
                    }

                    $member_name = $updatedMemberDetails->Names ?? null;
                    $date_of_birth = $updatedMemberDetails->dob ?? null;
                    $member_age = $updatedMemberDetails->MemberAge ?? null;
                    $member_no = $updatedMemberDetails->member_no ?? null;
                    $member_salary = $updatedMemberDetails->Salary ?? null;
                    $id_type = $updatedMemberDetails->IDType ?? null;
                    $id_number = $updatedMemberDetails->IDNumber ?? null;
                    $has_dependants = $updatedMemberDetails->HasDependants ?? null;
                    $endorsement_request = $updatedMemberDetails->EndRequest ?? null;

                    Log::channel('corporate_api')->info('Endorsement request: ' . json_encode($updatedMemberDetails, JSON_PRETTY_PRINT));

                    //save the updated member details to EndorsementMembers table
                    $requested_members = $this->britam_db->table('EndorsementMembers')->insertGetId([
                        'MemberId' => $policy_member_id,
                        'MemberName' => $member_name,
                        'DateOfBirth' => $date_of_birth,
                        'MemberAge' => $member_age,
                        'MemberNo' => $member_no,
                        'MemberSalary' => $member_salary,
                        'IdType' => $id_type,
                        'IdNumber' => $id_number,
                        'HasDependants' => $has_dependants,
                        'EndorsementRequest' => $endorsement_request,
                        'ExitDay' => $exit_day ?? now(),
                        'created_on' => date('Y-m-d H:i:s'),
                        'created_by' => 'API',
                    ]);
                } else if ($joiner_endorse_type != null) { // 2 being joiners

                    // check if the scheme has a category
                    $category = $this->britam_db->table('polschemeinfo')->select("with_categories")->where("schemeID", $scheme_id)->first();

                    if ($category->with_categories == 1) {
                        if ($category_code == null) {
                            return response()->json([
                                'success' => false,
                                'message' => 'Category code is required'
                            ], 400);
                        }
                    }

                    // check for UseMemberRate in the scheme 
                    $use_member_rate = $this->britam_db->table('polschemeinfo')->select("UseMemberRate")->where("schemeID", $scheme_id)->first();

                    // if it is true(1) then the premium rate is required else it is not
                    if ($use_member_rate->UseMemberRate == 1) {
                        if ($premium_rate == null) {
                            return response()->json([
                                'success' => false,
                                'message' => 'Premium rate is required'
                            ], 400);
                        }
                    }

                    $requested_members = $this->britam_db->table('EndorsementMembers')->insertGetId([
                        'MemberName' => $member_name,
                        'MemberEmail' => $member_email,
                        'DateOfBirth' => $date_of_birth,
                        'MemberAge' => $member_age,
                        'MemberNo' => $member_no,
                        'MemberSalary' => $new_member_salary,
                        'IdType' => $id_type,
                        'IdNumber' => $id_number,
                        'JoiningDate' => $effective_date,
                        'HasDependants' => $has_dependants,
                        'EndorsementRequest' => $request_id,
                        'created_on' => date('Y-m-d H:i:s'),
                        'created_by' => 'API',
                        'PremRate' => $premium_rate,
                        'Category' => $category_code,
                    ]);

                    $logged_requested_member = $this->britam_db->table('EndorsementMembers')->find($requested_members);
                    Log::channel('corporate_api')->info('Endorsement request: ' . json_encode($logged_requested_member, JSON_PRETTY_PRINT));

                    if (isset($request['dependants']) && is_array($request['dependants'])) {
                        $size_of_dependants = sizeof($request['dependants']);
                        Log::channel('corporate_api')->info('Endorsement request dependants: ' . $size_of_dependants);

                        if (($has_dependants == 1) && ($size_of_dependants > 0)) {
                            $memberDependants = [];
                            foreach ($request['dependants'] as $dependant) {
                                $age = $this->calculateAge($dependant['DependantDOB']);
                                $memberDependants[] = [
                                    'Member' => $requested_members,
                                    'DependantEmail' => $dependant_email ?? "",
                                    'FullName' => $dependant['DependantFullName'],
                                    'DateOfBirth' => $dependant['DependantDOB'],
                                    'DependantAge' => $age,
                                    'RelationshipType' => $dependant['RelationshipType'],
                                    'DependantJoiningDate' => $dependant['DependantJoiningDate'],
                                    'created_on' => date('Y-m-d H:i:s'),
                                    'created_by' => 'API'
                                ];
                            }

                            Log::channel('corporate_api')->info('Endorsement request: ' . json_encode($memberDependants, JSON_PRETTY_PRINT));

                            if (sizeof($memberDependants) > 0) {
                                $this->britam_db->table('EndorsementDependants')->insert($memberDependants);
                            }
                        } else if (($has_dependants == 1) && (sizeof($request['dependants']) == 0)) {
                            return response()->json([
                                'success' => false,
                                'message' => 'Dependants are required for this member'
                            ], 400);
                        } else if (($has_dependants == 0) && (sizeof($request['dependants']) > 0)) {
                            return response()->json([
                                'success' => false,
                                'message' => 'Dependants are not required for this member'
                            ], 400);
                        }
                    } else {
                        $size_of_dependants = 0;
                        Log::channel('corporate_api')->info('Endorsement request dependants: 0');
                    }
                } else if ($salary_rev_endorse_type != null) { // 6 being for salary change

                    $member_id_present = $this->britam_db->table('EndorsementMembers')->select("*")->where("MemberId", $policy_member_id)->first();

                    if ($member_id_present != null) {

                        // should check if the endorsement request tied to the member was already processed

                        $endorsement_request = $member_id_present->EndorsementRequest;

                        $endorse_status_processed = $this->britam_db->table('EndorsementRequestStatus')->where('IsProcessed', 1)->first();

                        // check the $endorsement_request against EndorsementRequest table and see if it has been processed
                        $endorsement_request_status = $this->britam_db->table('EndorsementRequest')->select("*")->where("Id", $endorsement_request)->where("Status", $endorse_status_processed->Id)->first();

                        if ($endorsement_request_status == null) {
                            return response()->json([
                                'success' => false,
                                'message' => 'Member already exists in an existing pending endorsement request'
                            ], 400);
                        }
                    }

                    if ($new_member_salary == null) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Member salary is required'
                        ], 400);
                    }

                    $this->britam_db->table('glmembersinfo')->where('MemberId', $policy_member_id)->update(['EndRequest' => $request_id]);

                    //get the updated member details to be updated to EndorsementMembers table
                    $updatedMemberDetails = $this->britam_db->table('glmembersinfo')->select("*")->where("MemberId", $policy_member_id)->where("SchemeID", $scheme_id)->where("EndRequest", $request_id)->first();

                    $member_name = $updatedMemberDetails->Names ?? null;
                    $date_of_birth = $updatedMemberDetails->dob ?? null;
                    $member_age = $updatedMemberDetails->MemberAge ?? null;
                    $member_no = $updatedMemberDetails->member_no ?? null;
                    $member_salary = $updatedMemberDetails->Salary ?? null;
                    $id_type = $updatedMemberDetails->IDType ?? null;
                    $id_number = $updatedMemberDetails->IDNumber ?? null;
                    $has_dependants = $updatedMemberDetails->HasDependants ?? null;
                    $endorsement_request = $updatedMemberDetails->EndRequest ?? null;


                    //save the updated member details to EndorsementMembers table
                    $requested_members = $this->britam_db->table('EndorsementMembers')->insertGetId([
                        'MemberId' => $policy_member_id,
                        'MemberName' => $member_name,
                        'DateOfBirth' => $date_of_birth,
                        'MemberAge' => $member_age,
                        'MemberNo' => $member_no,
                        'MemberSalary' => $member_salary,
                        'EffectiveDate' => $effective_date,
                        'NewMemberSalary' => $new_member_salary,
                        'IdType' => $id_type,
                        'IdNumber' => $id_number,
                        'HasDependants' => $has_dependants,
                        'EndorsementRequest' => $endorsement_request,
                        'created_on' => date('Y-m-d H:i:s'),
                        'created_by' => 'API',
                    ]);
                } else if ($joiner_dep_endorse_type != null) { // 7 being for adding dependants to existing members

                    // check if the scheme has a category
                    $category = $this->britam_db->table('polschemeinfo')->select("with_categories")->where("schemeID", $scheme_id)->first();

                    if ($category->with_categories == 1) {
                        if ($category_code == null) {
                            return response()->json([
                                'success' => false,
                                'message' => 'Category code is required'
                            ], 400);
                        }
                    }

                    // check for UseMemberRate in the scheme 
                    $use_member_rate = $this->britam_db->table('polschemeinfo')->select("UseMemberRate")->where("schemeID", $scheme_id)->first();

                    // if it is true(1) then the premium rate is required else it is not
                    if ($use_member_rate->UseMemberRate == 1) {
                        if ($premium_rate == null) {
                            return response()->json([
                                'success' => false,
                                'message' => 'Premium rate is required'
                            ], 400);
                        }
                    }

                    $this->britam_db->table('glmembersinfo')->where('MemberId', $policy_member_id)->update(['EndRequest' => $request_id]);
                    $this->britam_db->table('glmembersinfo')->where('MemberId', $policy_member_id)->update(['HasDependants' => 1]);

                    $updatedMemberDetails = $this->britam_db->table('glmembersinfo')->select("*")->where("MemberId", $policy_member_id)->where("SchemeID", $scheme_id)->where("EndRequest", $request_id)->first();

                    if ($updatedMemberDetails == null) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Member does not exist'
                        ], 400);
                    }

                    Log::channel('corporate_api')->info('Joiner Endorsement request: ' . json_encode($updatedMemberDetails, JSON_PRETTY_PRINT));

                    $member_name = $updatedMemberDetails->Names ?? null;
                    $date_of_birth = $updatedMemberDetails->dob ?? null;
                    $member_age = $updatedMemberDetails->MemberAge ?? null;
                    $member_no = $updatedMemberDetails->member_no ?? null;
                    $member_salary = $updatedMemberDetails->Salary ?? null;
                    $id_type = $updatedMemberDetails->IDType ?? null;
                    $id_number = $updatedMemberDetails->IDNumber ?? null;
                    $has_dependants = $updatedMemberDetails->HasDependants ?? null;
                    $endorsement_request = $updatedMemberDetails->EndRequest ?? null;


                    //save the updated member details to EndorsementMembers table
                    $requested_members = $this->britam_db->table('EndorsementMembers')->insertGetId([
                        'MemberName' => $member_name,
                        'IsDependantAddition' => 1,
                        'DateOfBirth' => $date_of_birth,
                        'MemberAge' => $member_age,
                        'MemberNo' => $member_no,
                        'MemberSalary' => $member_salary,
                        'EffectiveDate' => $effective_date,
                        'NewMemberSalary' => $new_member_salary,
                        'IdType' => $id_type,
                        'IdNumber' => $id_number,
                        'HasDependants' => $has_dependants,
                        'EndorsementRequest' => $endorsement_request,
                        'created_on' => date('Y-m-d H:i:s'),
                        'created_by' => 'API',
                        'PremRate' => $premium_rate,
                        'Category' => $category_code,
                    ]);

                    $logged_requested_member = $this->britam_db->table('EndorsementMembers')->find($requested_members);

                    Log::channel('corporate_api')->info('Endorsement request: ' . json_encode($logged_requested_member, JSON_PRETTY_PRINT));

                    if (isset($request['dependants']) && is_array($request['dependants'])) {
                        $size_of_dependants = sizeof($request['dependants']);
                        Log::channel('corporate_api')->info('Endorsement request dependants: ' . $size_of_dependants);

                        if ($size_of_dependants > 0) {
                            $memberDependants = [];
                            foreach ($request['dependants'] as $dependant) {
                                $age = $this->calculateAge($dependant['DependantDOB']);
                                $memberDependants[] = [
                                    'Member' => $requested_members,
                                    'DependantEmail' => $dependant['DependantEmail'] ?? null,
                                    'FullName' => $dependant['DependantFullName'],
                                    'DateOfBirth' => $dependant['DependantDOB'],
                                    'DependantAge' => $age,
                                    'RelationshipType' => $dependant['RelationshipType'],
                                    'DependantJoiningDate' => $dependant['DependantJoiningDate'],
                                    'created_on' => date('Y-m-d H:i:s'),
                                    'created_by' => 'API'
                                ];
                            }

                            Log::channel('corporate_api')->info('Endorsement request: ' . json_encode($memberDependants, JSON_PRETTY_PRINT));

                            if (sizeof($memberDependants) > 0) {
                                $this->britam_db->table('EndorsementDependants')->insert($memberDependants);
                            }
                        } else if (($has_dependants == 1) && (sizeof($request['dependants']) == 0)) {
                            return response()->json([
                                'success' => false,
                                'message' => 'Dependants are required for this member'
                            ], 400);
                        } else if (($has_dependants == 0) && (sizeof($request['dependants']) > 0)) {
                            return response()->json([
                                'success' => false,
                                'message' => 'Dependants are not required for this member'
                            ], 400);
                        }
                    } else {
                        $size_of_dependants = 0;
                        Log::channel('corporate_api')->info('Endorsement request dependants: 0');
                    }
                }

                if ($request->hasFile('files')) {

                    $fileUploadData = $request->file('files');

                    $file_ids = [];

                    foreach ($fileUploadData as $code => $file) {
                        $fileName = time() . '_' . $file->getClientOriginalName();
                        $file_extension = $file->getClientOriginalExtension();
                        $file_size = $file->getSize();
                        $file_mime = $file->getMimeType();
                        //file_path = $file->storeAs('endorsement_requests', $file_name, 'local');
                        $storedFilePath =
                            Storage::disk('public_documents')->putFileAs(
                                'endorsement_docs',
                                $file,
                                $fileName
                            );

                        $file_path = Storage::disk('public_documents')->path($storedFilePath);

                        $category = $this->britam_db->table('GroupLifeFileCategories')->select("ID")->where("IsEndorsementDocument", true)->first();

                        $file_id = $this->britam_db->table('EndorsementRequestSupportDocs')->insertGetId([
                            'EndorsementRequestId' => $request_id,
                            'FileCategory' => $category->ID ?? 8,
                            'DocumentType' => $code,
                            'FileName' => $fileName,
                            'FileExtension' => $file_extension,
                            'FileSize' => $file_size,
                            'FileMime' => $file_mime,
                            'FullFilePath' => $file_path,
                            'created_on' => date('Y-m-d H:i:s'),
                            'created_by' => 'API'
                        ]);

                        $file_ids[] = $file_id;
                    }
                }

                if (($request_id > 0) && ($requested_members > 0)) {

                    $token = $request->bearerToken();

                    // get the email address of the client the contact person is representing

                    $portal_user_id = $this->britam_db->table('PortalUserLoginInfo')->select('ContactPerson')->where('Id', $login_user_id)->first();

                    if ($portal_user_id == null) {
                        $this->britam_db->rollBack();
                        return response()->json([
                            'success' => false,
                            'message' => 'Invalid contact person'
                        ], 400);
                    }

                    if (Config::get('app.env') == 'production') {
                        // contact person who initiated
                        $portal_user_id = $portal_user_id->ContactPerson;

                        // get their email
                        $contact_person_email_who_initiated = $this->britam_db->table('contactpersoninfo')->select('contact_email')->where('id', $portal_user_id)->first();

                        // get all contact persons with the same scheme access in ClientSchemesAccess and send them each a message of the change
                        $scheme_access = $this->britam_db->table('ClientSchemesAccess')->select('ContactPersonReferred')->where('Scheme', $scheme_id)->get();

                        // foreach get their email address
                        foreach ($scheme_access as $contact_person) {
                            $contact_person_email = $this->britam_db->table('contactpersoninfo')->select('contact_email')->where('id', $contact_person->ContactPersonReferred)->first();

                            if ($contact_person_email != null) {
                                $contact_person_email = $contact_person_email->contact_email;
                                $this->sendEndorsementRequestNotification($request_id, $scheme_id, $endorsement_type, $effective_date, $requested_change, $token, $contact_person_email, $contact_person_email_who_initiated);
                            }
                        }
                    }

                    $policy_no = $this->britam_db->table('polschemeinfo')->select("policy_no")->where("schemeID", $scheme_id)->first();

                    //$response = $this->processEndorsementRequest($request_id);
                    //commit
                    $this->britam_db->commit();

                    return response()->json([
                        'success' => true,
                        'message' => 'Request created successfully',
                        'data' => [
                            //add file_id to the response if the file was uploaded
                            //'automation_results' => $response,
                            'request_id' => $request_id,
                            'request_number' => $request_number,
                            'policy_number' => $policy_no->policy_no ?? null,
                            'file_id' => $file_ids ?? null,
                        ]
                    ], 200);
                } else {
                    //rollback
                    $this->britam_db->rollBack();

                    return response()->json([
                        'success' => false,
                        'message' => 'Error creating request'
                    ], 500);
                }
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'No members found'
                ], 404);
            }
        } catch (\Throwable $th) {

            $this->britam_db->rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error creating request ' . $th->getMessage()
            ], 500);
        }
    }


    // Credit life endorsement request
    // similar to the group life endorsement request

    public function credit_life_endorsement_request(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'scheme_id' => 'required|integer',
            'endorsement_type' => 'required|integer',
            'date_of_loan' => 'required|date',
            'portal_user_id' => 'required', // user making the request
            'policy_member_id' => 'string',
            'requested_change' => 'required|string',
            'MemberName' => 'string',
            'DateOfBirth' => 'date',
            'MemberNo' => 'string',
            'LoanAmount' => 'numeric',
            'MemberEmail' => 'nullable',
            'DependantEmail' => 'nullable',
            'IdType' => 'string',
            'IdNumber' => 'string',
            'Exitday' => 'date',
            'duration' => 'integer',
            'interest_rate' => 'required',
            'loan_number' => 'required',
            'id_number' => 'required',
            'is_joint_life' => 'boolean',
            'retrenchment_applicable' => 'boolean',
            'premium_rate' => 'nullable',
            'category_code' => 'required',
            // 'HasDependants' => 'integer',
            // 'dependants' => 'array',
            // 'dependants.*.DependantFullName' => 'string',
            // 'dependants.*.DependantDOB' => 'date',
            // 'dependants.*.RelationshipType' => 'string',
            // 'dependants.*.DependantJoiningDate' => 'date',
            // 'files' => 'array',
            // 'files.*' => 'file|mimes:pdf,jpg,jpeg,png,xls,xlsx,tiff|max:21474836480'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid request data',
                'data' => $validator->errors()
            ], 400);
        }

        try {

            $scheme_id = $request->input('scheme_id');
            $endorsement_type = $request->input('endorsement_type');
            $effective_date = $request->input('date_of_loan');
            $requested_change = $request->input('requested_change');
            $policy_member_id = $request->input('policy_member_id');
            $member_name = $request->input('MemberName');
            $date_of_birth = $request->input('DateOfBirth');
            $member_no = $request->input('MemberNo');
            $loan_amount = $request->input('LoanAmount');
            $member_email = $request->input('MemberEmail');
            $dependant_email = $request->input('DependantEmail');
            $id_type = $request->input('IdType');
            $id_number = $request->input('IdNumber');
            $exit_day = $request->input('Exitday');
            $duration = $request->input('duration');
            $interest_rate = $request->input('interest_rate');
            $loan_number = $request->input('loan_number');
            $id_number = $request->input('id_number');
            $is_joint_life = $request->input('is_joint_life');
            $retrenchment_applicable = $request->input('retrenchment_applicable');
            $premium_rate = $request->input('premium_rate');
            $category_code = $request->input('category_code');

            // if is_joint_life and retrenchment_applicable is 0 then set them to null
            if ($is_joint_life == 0) {
                $is_joint_life = null;
            }
            if ($retrenchment_applicable == 0) {
                $retrenchment_applicable = null;
            }

            // the endorsement_types are as follows
            // 1 - New Member
            // 2 - Early settlement
            // 3 - Top up
            // 4 - Change of loan amount
            // 5 - Change of loan duration


        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating request ' . $th->getMessage()
            ], 500);
        }
    }


    public function addDependantsToEndosementReqMembers(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'scheme_id' => 'required|integer',
            'endorsement_type' => 'required|integer',
            'effective_date' => 'required|date',
            'policy_member_id' => 'string',
            'requested_change' => 'required|string',
            'dependants' => 'required|array',
            'dependants.*.DependantFullName' => 'required|string',
            'dependants.*.DependantDOB' => 'required|date',
            'dependants.*.RelationshipType' => 'required|string',
            'dependants.*.EndMemberId' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid request data',
                'data' => $validator->errors()
            ], 400);
        }

        try {

            $scheme_id = $request->input('scheme_id');
            $endorsement_type = $request->input('endorsement_type');
            $effective_date = $request->input('effective_date');
            $requested_change = $request->input('requested_change');
            $policy_member_id = $request->input('policy_member_id');

            //$this->britam_db->table('glmembersinfo')->where('MemberId', $policy_member_id)->update(['EndRequest' => $request_id]);
            $request_number = $this->generateRequestNumber(true, false, $scheme_id);

            $request_id = $this->britam_db->table('EndorsementRequest')->insertGetId([
                'EndorsementRequestNumber' => $request_number,
                'PolicyNo' => $scheme_id,
                'EndorsementType' => $endorsement_type,
                'EffectiveDate' => $effective_date,
                'RequestedChanges' => $requested_change,
                'Status' => 1,
                'created_on' => date('Y-m-d H:i:s'),
                'created_by' => 'API'
            ]);

            $member_id_present = $this->britam_db->table('EndorsementMembers')->select("*")->where("MemberId", $policy_member_id)->first();

            $memberDependants = [];
            foreach ($request['dependants'] as $dependant) {
                $age = $this->calculateAge($dependant['DependantDOB']);
                $memberDependants[] = [
                    'Member' => $dependant['EndMemberId'],
                    'FullName' => $dependant['DependantFullName'],
                    'DateOfBirth' => $dependant['DependantDOB'],
                    'DependantAge' => $age,
                    'RelationshipType' => $dependant['RelationshipType'],
                    'created_on' => date('Y-m-d H:i:s'),
                    'created_by' => 'API'
                ];
            }

            // Confirm if the endmember has dependents enabled
            $firstDependant = reset($memberDependants); // Get the first element of the array

            $member = $this->britam_db->table('EndorsementMembers')
                ->select("*")
                ->where("Id", $firstDependant['Member'])
                ->first();

            if (!$member || $member->HasDependants == 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Member does not have dependants'
                ], 400);
            }

            $results = $this->britam_db->table('EndorsementDependants')->insert($memberDependants);

            if ($results > 0) {
                return response()->json([
                    'success' => true,
                    'message' => 'Dependant(s) added successfully'
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Error adding dependant'
                ], 500);
            }
        } catch (\Throwable $th) {
            //throw $th;
            return response()->json([
                'success' => false,
                'message' => 'Error adding dependant' . $th->getMessage()
            ], 500);
        }
    }

    public function getRequestedEndorsements(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'scheme_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid request data',
                'data' => $validator->errors()
            ], 400);
        }

        try {
            $scheme_id = $request->input('scheme_id');

            $results = $this->britam_db->table('EndorsementRequest AS er')
                ->leftJoin('EndorsementMembers AS em', 'er.Id', '=', 'em.EndorsementRequest')
                ->leftJoin('EndorsementDependants AS ed', 'em.Id', '=', 'ed.Member')
                ->join('glifeEndorsementType AS et', 'et.Id', '=', 'er.EndorsementType')
                ->leftJoin('EndorsementRequestFiles AS ef', 'ef.EndorsementRequest', '=', 'er.Id')
                ->leftJoin('EndorsementRequestSupportDocs AS ers', 'ers.EndorsementRequestId', '=', 'er.Id')
                ->join('EndorsementRequestStatus AS es', 'es.Id', '=', 'er.Status')
                ->select(
                    'er.Id AS RequestId',
                    'ef.FileName',
                    'ef.FileUrl',
                    'em.Id AS MemberId',
                    'er.IsBulkUpload',
                    'er.EndorsementRequestNumber',
                    'er.created_on AS RequestDate',
                    'em.EndorsementRequest',
                    'em.MemberName',
                    'em.MemberAge',
                    'em.MemberNo',
                    'em.MemberSalary',
                    'em.IdType',
                    'em.IdNumber',
                    'em.HasDependants',
                    'er.RequestedChanges',
                    'et.Description',
                    'ed.FullName',
                    'es.Description AS RequestStatus',
                    'ed.DependantAge',
                    'ed.RelationshipType',
                    'er.*',
                    'ed.*',
                    'ers.FileName AS SupportDocFileName',
                    'ers.FullFilePath AS SupportDocFileUrl'
                )
                ->where('er.PolicyNo', $scheme_id)
                ->get();

            Log::channel('corporate_api')->info('Endorsement request: ' . json_encode($results, JSON_PRETTY_PRINT));

            if (sizeof($results) > 0) {
                $structuredData = [];

                foreach ($results as $result) {
                    // Use the main member's ID as the array key
                    $memberId = $result->MemberId;
                    $request_id = $result->RequestId;
                    $policy_no = $this->britam_db->table('polschemeinfo')->select("policy_no")->where("schemeID", $scheme_id)->first()->policy_no;
                    $bulk_upload = $result->IsBulkUpload;

                    if ($bulk_upload == 0 || $bulk_upload == null) {

                        if (!isset($structuredData[$request_id])) {
                            $structuredData[$request_id] = [
                                'scheme_id' => $scheme_id,
                                'bulk_upload' => $bulk_upload,
                                'policy_number' => $policy_no,
                                'EndorsementType' => $result->Description,
                                'MemberName' => $result->MemberName,
                                'MemberAge' => $result->MemberAge,
                                'MemberNo' => $result->MemberNo,
                                'MemberSalary' => $result->MemberSalary,
                                'IdType' => $result->IdType,
                                'IdNumber' => $result->IdNumber,
                                'HasDependants' => $result->HasDependants,
                                'EndorsementRequest' => $result->EndorsementRequest,
                                'Status' => $result->RequestStatus,
                                'SupportFileName' => $result->SupportDocFileName,
                                'SupportFileUrl' => $result->SupportDocFileUrl,
                                'CreatedOn' => $result->RequestDate,
                                'dependents' => [], // Initialize an empty array
                            ];
                        }

                        if ($result->HasDependants != 0) {
                            $structuredData[$memberId]['dependents'][] = [
                                'FullName' => $result->FullName,
                                'DependantAge' => $result->DependantAge,
                                'RelationshipType' => $result->RelationshipType,
                            ];
                        }
                    } else if ($bulk_upload == 1) {

                        if (!isset($structuredData[$request_id])) {
                            $structuredData[$request_id] = [
                                'scheme_id' => $scheme_id,
                                'bulk_upload' => $bulk_upload,
                                'policy_number' => $policy_no,
                                'EndorsementType' => $result->Description,
                                'RequestNumber' => $result->EndorsementRequestNumber,
                                'request_id' => $request_id,
                                'Status' => $result->RequestStatus,
                                'file_name' => $result->FileName,
                                'file_url' => $result->FileUrl,
                                'CreatedOn' => $result->RequestDate,
                            ];
                        }
                    }

                    Log::channel('corporate_api')->info('Endorsement request: ' . json_encode($structuredData, JSON_PRETTY_PRINT));
                }

                $structuredData = array_values($structuredData);

                return response()->json([
                    'success' => true,
                    'message' => 'Endorsement requests fetched successfully',
                    'count' => count($structuredData),
                    'data' => $structuredData,
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'No requests found'
                ], 404);
            }
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching data' . $th->getMessage()
            ], 500);
        }
    }


    public function getSchemeRiders(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'scheme_id' => 'nullable',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid request data',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $scheme_id = $request->input('scheme_id');

            if ($scheme_id != null) {

                //confirm if it is a corporate institution
                $client_type = $this->britam_db->table('glifeclientinfo', 'g')
                    ->join('polschemeinfo as p', 'p.ClientNumber', '=', 'g.Id')
                    //->join('glmembersinfo as gm', 'gm.SchemeID', '=', 'p.schemeID')
                    ->where('p.SchemeID', $scheme_id)
                    ->select('g.client_type')
                    ->first();

                if ($client_type == null) {
                    return response()->json([
                        'success' => false,
                        'message' => 'No results found.',
                        'data' => []
                    ], 400);
                    // } else {
                    //     $client_type = $client_type->client_type;

                    //     if ($client_type != 1) {
                    //         return response()->json([
                    //             'success' => false,
                    //             'message' => 'Scheme is not for a corporate institution.'
                    //         ], 400);
                } else {
                    //SELECT gi.* FROM glife_pol_riders g 
                    // INNER JOIN polschemeinfo p ON p.schemeID = g.schemeID
                    // INNER JOIN glife_plan_rider_config gp ON gp.id = g.RiderId
                    // INNER JOIN gliferider_info gi ON gi.rider_code = gp.rider_code
                    // WHERE p.schemeID = 5; 

                    // SELECT gi.*, gtt.description AS CategoryName
                    // FROM glife_pol_riders g
                    // INNER JOIN glifecateg gtt ON gtt.id = g.CategoryID
                    // INNER JOIN polschemeinfo p ON p.schemeID = g.schemeID
                    // INNER JOIN glife_plan_rider_config gp ON gp.id = g.RiderId
                    // INNER JOIN gliferider_info gi ON gi.rider_code = gp.rider_code
                    // WHERE p.schemeID = 20;

                    $is_with_categories = $this->britam_db->table('polschemeinfo')
                        ->select('with_categories')
                        ->where('schemeID', $scheme_id)
                        ->get();


                    Log::channel('corporate_api')->info('Endorsement request: ' . json_encode($is_with_categories, JSON_PRETTY_PRINT));

                    $categories_val = $is_with_categories[0]->with_categories;

                    Log::channel('corporate_api')->info('Endorsement request: ' . json_encode($categories_val, JSON_PRETTY_PRINT));

                    if ($categories_val == 1) {
                        $results = $this->britam_db->table('glife_pol_riders as g')
                            ->join('glifecateg as gtt', 'gtt.id', '=', 'g.CategoryID')
                            ->join('polschemeinfo as p', 'p.schemeID', '=', 'g.schemeID')
                            ->join('glife_plan_rider_config as gp', 'gp.id', '=', 'g.RiderId')
                            ->join('gliferider_info as gi', 'gi.rider_code', '=', 'gp.rider_code')
                            ->select('gi.*', 'gtt.description AS CategoryName', 'g.*', \DB::raw("
                                                CASE 
                                                    WHEN g.perc_payable <= 0.0 THEN 'N/A'
                                                    ELSE CAST(g.perc_payable AS VARCHAR(255))
                                                END AS perc_payable
                                            "))
                            ->where('p.schemeID', $scheme_id)
                            ->get();
                    } else {
                        $results = $this->britam_db->table('glife_pol_riders as g')
                            ->join('polschemeinfo as p', 'p.schemeID', '=', 'g.schemeID')
                            ->join('glife_plan_rider_config as gp', 'gp.id', '=', 'g.RiderId')
                            ->join('gliferider_info as gi', 'gi.rider_code', '=', 'gp.rider_code')
                            ->select('gi.*', 'g.*', \DB::raw("
                                                CASE 
                                                    WHEN g.perc_payable <= 0.0 THEN 'N/A'
                                                    ELSE CAST(g.perc_payable AS VARCHAR(255))
                                                END AS perc_payable
                                            "))
                            ->where('p.schemeID', $scheme_id)
                            ->get();
                    }

                    if (sizeof($results) > 0) {
                        return response()->json([
                            'success' => true,
                            'message' => 'Scheme riders fetched successfully',
                            'count' => count($results),
                            'data' => $results
                        ], 200);
                    } else {
                        return response()->json([
                            'success' => false,
                            'message' => 'No riders found'
                        ], 200);
                    }
                }
            } else {

                //SELECT * FROM gliferider_info g;
                $results = $this->britam_db->table('gliferider_info as g')
                    ->select('g.*')
                    ->get();

                if ($results != null) {
                    return response()->json([
                        'success' => true,
                        'message' => 'Riders fetched successfully',
                        'count' => count($results),
                        'data' => $results
                    ], 200);
                } else {

                    return response()->json([
                        'success' => true,
                        'message' => 'No riders found'
                    ], 204);
                }
            }
        } catch (\Throwable $th) {
            //throw $th;
            return response()->json([
                'success' => false,
                'message' => 'Error fetching data' . $th->getMessage()
            ], 500);
        }
    }

    public function getDepedantsAndBenefUnderMember(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'scheme_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => true,
                'message' => 'Invalid request data',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            // SELECT * FROM glmembersinfo g
            // INNER JOIN GlifeDependants gd ON gd.MemberId = g.MemberId
            // WHERE g.SchemeID = 6;


            $scheme_id = $request->input('scheme_id');

            $results = $this->britam_db->table('glmembersinfo as g')
                ->join('GlifeDependants as gd', 'gd.MemberId', '=', 'g.MemberId')
                ->select('g.*', 'gd.*')
                ->where('g.SchemeID', $scheme_id)
                ->get();

            if (sizeof($results) > 0) {
                return response()->json([
                    'success' => true,
                    'message' => 'Dependants and beneficiaries fetched successfully',
                    'count' => count($results),
                    'data' => $results
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'No dependants and beneficiaries found'
                ], 404);
            }
        } catch (\Throwable $th) {
            //throw $th;
            return response()->json([
                'success' => false,
                'message' => 'Error fetching data' . $th->getMessage()
            ], 500);
        }
    }

    public function getDependantsUnderMember(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'member_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid request data',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $member_id = $request->input('member_id');


            //SELECT gd.* FROM glmembersinfo g
            // INNER JOIN GlifeDependants gd ON gd.MemberId = g.MemberId
            // WHERE gd.MemberId = 568636;

            $results = $this->britam_db->table('GlifeDependants as gd')
                ->select('gd.*')
                ->where('gd.MemberId', $member_id)
                ->get();

            if (sizeof($results) > 0) {
                return response()->json([
                    'success' => true,
                    'message' => 'Dependants fetched successfully',
                    'count' => count($results),
                    'data' => $results
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'No dependants found'
                ], 404);
            }
        } catch (\Throwable $th) {
            //throw $th;
            return response()->json([
                'success' => false,
                'message' => 'Error fetching data' . $th->getMessage()
            ], 500);
        }
    }

    public function getBeneficiariesUndMember(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'member_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid request data',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $member_id = $request->input('member_id');

            //SELECT gb.* FROM glmembersinfo g
            // INNER JOIN GlifeBeneficiaries gb ON gb.MemberId = g.MemberId
            // WHERE gb.MemberId = 568636;

            $results = $this->britam_db->table('glifebeneficiary_info as gb')
                ->select('gb.*')
                ->where('gb.MemberId', $member_id)
                ->get();

            if (sizeof($results) > 0) {
                return response()->json([
                    'success' => true,
                    'message' => 'Beneficiaries fetched successfully',
                    'count' => count($results),
                    'data' => $results
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'No beneficiaries found'
                ], 404);
            }
        } catch (\Throwable $th) {
            //throw $th;
            return response()->json([
                'success' => false,
                'message' => 'Error fetching data' . $th->getMessage()
            ], 500);
        }
    }

    public function getPolicyCoverPeriods(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'client_type' => 'required',
            'contact_persons_id' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid request data',
                'errors' => $validator->errors()
            ], 400);
        }

        try {

            $currentDate = date('Y-m-d');
            $client_type = $request->input('client_type');
            $login_user_id = $request->input('contact_persons_id');

            //get contactpersonid from the portalloginuser
            $cp_id = $this->britam_db->table('PortalUserLoginInfo')->select('GroupClient', 'ContactPerson')->where('Id', $login_user_id)->first();

            if ($cp_id == null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid contact person'
                ], 400);
            }

            $contact_persons_id = $cp_id->ContactPerson;
            $cliendId = $cp_id->GroupClient;

            $results = [];

            if ($client_type == 1) {
                //lets get the client id from the contact person id
                //$client_id = $this->britam_db->table('PortalUserLoginInfo')->where('Id', $contact_persons_id)->first()->GroupClient;
                $results = $this->britam_db->table('polschemeinfo as p')
                    ->select('p.schemeID', 'p.DateFrom', 'p.End_date', 'p.PolAnniversary', \DB::raw("CASE 
                            WHEN p.SchemeDescription IS NOT NULL THEN CONCAT(p.SchemeDescription, ' - ', p.policy_no)
                            WHEN p.CompanyName IS NOT NULL THEN CONCAT(p.CompanyName, ' - ', p.policy_no)
                            ELSE p.policy_no
                        END AS PolicyCompany"), 'gs.Description AS Status')
                    ->join('glifestatus as gs', 'gs.status_code', '=', 'p.StatusCode')
                    //->join('ClientSchemesAccess as ca', 'ca.scheme', '=', 'p.schemeID')
                    ->where(function ($query) {
                        $query->where('p.StatusCode', '001')
                            ->orWhere('p.StatusCode', '005')
                            ->orWhere('p.StatusCode', '010');
                    })
                    //lets use dates
                    //->whereRaw('? BETWEEN p.DateFrom AND p.End_date', [$currentDate])
                    //->where('ca.ContactPersonReferred', $contact_persons_id)
                    //->where('ca.AllowAccess', 1)
                    ->where('p.ClientNumber', $cliendId)
                    ->get();
            } else if ($client_type == 2) {
                //get the intermediary Id,
                $loginObj = $this->britam_db->table('PortalUserLoginInfo')->where('Id', $contact_persons_id)->first();
                $brokerId = $loginObj->Broker;

                $results = $this->britam_db->table('polschemeinfo as p')
                    ->join('glifestatus as gs', 'gs.status_code', '=', 'p.StatusCode')
                    ->join('glifeclientinfo as q', 'p.ClientNumber', '=', 'q.Id')
                    ->select('p.schemeID', 'p.DateFrom', 'p.End_date', 'p.PolAnniversary','q.name as CompanyName', 
                    \DB::raw("CASE 
                            WHEN p.SchemeDescription IS NOT NULL THEN CONCAT(p.SchemeDescription, ' - ', p.policy_no)
                            WHEN q.name IS NOT NULL THEN CONCAT(q.name, ' - ', p.policy_no)
                            ELSE p.policy_no
                        END AS PolicyCompany"), 'gs.Description AS Status')
                    //->join('ClientSchemesAccess as ca', 'ca.scheme', '=', 'p.schemeID')
                    ->where(function ($query) {
                        $query->where('p.StatusCode', '001')
                            ->orWhere('p.StatusCode', '005');
                    })
                    ->where('p.interm_ID', $brokerId)
                    //->where('ca.ContactPersonReferred', $contact_persons_id)
                    //->where('ca.AllowAccess', 1)
                    ->whereRaw('? BETWEEN p.DateFrom AND p.End_date', [$currentDate])
                    ->get();
            }


            if (sizeof($results) > 0) {
                return response()->json([
                    'success' => true,
                    'message' => 'Policy cover periods fetched successfully',
                    'count' => count($results),
                    'data' => $results
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'No policy cover periods found'
                ], 404);
            }
        } catch (\Throwable $th) {
            //throw $th;
            return response()->json([
                'success' => false,
                'message' => 'Error fetching data' . $th->getMessage()
            ], 500);
        }
    }

    public function uploadEndoresementExcelDocument(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'scheme_id' => 'required',
            'portal_user_id' => 'required',
            'endorsement_type' => 'required',
            'effective_date' => 'required',
            'requested_change' => 'required',
            'file' => 'required|mimes:xlsx,xls,csv,txt',
            'files' => 'array',
            'support_files.*' => 'mimes:xlsx,xls,pdf,doc,docx,jpg,jpeg,png|max:21474836480',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid request data',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $scheme_id = $request->input('scheme_id');
            $endorsement_type = $request->input('endorsement_type');
            $effective_date = $request->input('effective_date');
            $requested_change = $request->input('requested_change');

            $file = $request->file('file');
            $file_name = time() . '_' . $file->getClientOriginalName();
            //remove all the spaces from the file name
            $file_name = str_replace(' ', '', $file_name);
            $file_extension = $file->getClientOriginalExtension();
            $file_size = $file->getSize();
            $file_mime = $file->getMimeType();

            //$file_path = $file->storeAs('endorsement_docs', $file_name, 'public_documents');
            $portal_user_id = $request->input('portal_user_id');

            // // $storedFilePath = Storage::disk('public_documents')->putFileAs('claim_documents', $fileData, $filename);
            // // $realFilePath = Storage::disk('public_documents')->path($storedFilePath);

            // //$file_url = Storage::disk('public_documents')->url($file_path);
            // $fullFileUrl = Storage::disk('public_documents')->path($file_path);

            $request_number = $this->generateRequestNumber(true, false, $scheme_id);

            Storage::disk('ftp')->putFileAs($file, $file_name);

            $fullFileUrl = Storage::disk('ftp')->url($file_name);
            $file_path = Storage::disk('ftp')->path($file_name);

            $request_id = $this->britam_db->table('EndorsementRequest')->insertGetId([
                'ContactPerson' => $portal_user_id,
                'EndorsementRequestNumber' => $request_number,
                'PolicyNo' => $scheme_id,
                'EndorsementType' => $endorsement_type,
                'EffectiveDate' => $effective_date,
                'RequestedChanges' => $requested_change,
                'IsBulkUpload' => 1,
                'Status' => 1,
                'created_on' => date('Y-m-d H:i:s'),
                'created_by' => 'API'
            ]);

            $file_data = [
                'EndorsementRequest' => $request_id,
                'FileName' => $file_name,
                'FileExtension' => $file_extension,
                'FileSize' => $file_size,
                'FileMime' => $file_mime,
                'FilePath' => $file_path,
                'FileUrl' => $fullFileUrl,
                'created_on' => date('Y-m-d H:i:s'),
                'created_by' => 'API'
            ];

            $file_id = $this->britam_db->table('EndorsementRequestFiles')->insertGetId($file_data);

            if ($request->hasFile('support_files')) {
                $fileUploadData = $request->file('support_files');

                $file_ids = [];

                foreach ($fileUploadData as $code => $file) {
                    $file_name = time() . '_' . $file->getClientOriginalName();
                    $file_extension = $file->getClientOriginalExtension();
                    $file_size = $file->getSize();
                    $file_mime = $file->getMimeType();
                    //file_path = $file->storeAs('endorsement_requests', $file_name, 'local');
                    // $storedFilePath = Storage::disk('public_documents')->putFileAs('endorsement_docs', $file, $file_name);

                    // $file_path = Storage::disk('public_documents')->path($storedFilePath);

                    Storage::disk('ftp')->putFileAs($file, $file_name);

                    //$fullFileUrl = Storage::disk('ftp')->url($file_name);
                    $file_path = Storage::disk('ftp')->path($file_name);

                    $category =
                        $this->britam_db->table('GroupLifeFileCategories')->select("ID")->where(
                            "IsEndorsementDocument",
                            true
                        )->first();

                    $support_file_id = $this->britam_db->table('EndorsementRequestSupportDocs')->insertGetId([
                        'EndorsementRequestId' => $request_id,
                        'FileCategory' => $category->ID ?? 8,
                        'DocumentType' => $code,
                        'FileName' => $file_name,
                        'FileExtension' => $file_extension,
                        'FileSize' => $file_size,
                        'FileMime' => $file_mime,
                        'FullFilePath' => $file_path,
                        'created_on' => date('Y-m-d H:i:s'),
                        'created_by' => 'API'
                    ]);

                    $file_ids[] = $support_file_id;
                }
            }

            if ($file_id > 0) {

                $token = $request->bearerToken();

                $portal_user_idKey = $this->britam_db->table('PortalUserLoginInfo')->select('ContactPerson')->where('Id', $portal_user_id)->first();

                if ($portal_user_idKey == null) {
                    $this->britam_db->rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid contact person'
                    ], 400);
                }

                if (Config::get('app.env') == 'production') {
                    // contact person who initiated
                    $portal_user_id = $portal_user_idKey->ContactPerson;

                    // get their email
                    $contact_person_email_who_initiated = $this->britam_db->table('contactpersoninfo')->select('contact_email')->where('id', $portal_user_id)->first();

                    // get all contact persons with the same scheme access in ClientSchemesAccess and send them each a message of the change
                    $scheme_access = $this->britam_db->table('ClientSchemesAccess')->select('ContactPersonReferred')->where('Scheme', $scheme_id)->get();

                    // foreach get their email address
                    foreach ($scheme_access as $contact_person) {
                        $contact_person_email = $this->britam_db->table('contactpersoninfo')->select('contact_email')->where('id', $contact_person->ContactPersonReferred)->first();

                        if ($contact_person_email != null) {
                            $contact_person_email = $contact_person_email->contact_email;
                            $this->sendEndorsementRequestNotification($request_id, $scheme_id, $endorsement_type, $effective_date, $requested_change, $token, $contact_person_email, $contact_person_email_who_initiated);
                        }
                    }
                }

                return response()->json([
                    'success' => true,
                    'message' => 'File uploaded successfully',
                    'data' => [
                        //'automation_response' => $response,
                        'request_id' => $request_id,
                        'file_id' => $file_id,
                        'file_url' => $fullFileUrl,
                        'support_file_id' => $file_ids ?? null,
                    ]
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Error uploading file'
                ], 500);
            }
        } catch (\Throwable $th) {
            //throw $th;
            return response()->json([
                'success' => false,
                'message' => 'Error uploading file' . $th->getMessage()
            ], 500);
        }
    }

    public function getRExtraPremiumDebitNotes(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'scheme_id' => 'required',
            'member_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid request data',
                'errors' => $validator->errors()
            ], 400);
        }

        //SELECT * FROM debitmastinfo d WHERE d.schemeNo = 8 AND d.endorsement_type = 5 AND d.client_no = 7;
        try {
            $scheme_id = $request->input('scheme_id');
            $member_id = $request->input('member_id');

            $endorsement_no = $this->britam_db->table('glsuppinfo as g')
                ->join('glsuppdetail as gd', 'gd.EndorseId', '=', 'g.id')
                ->select('g.endorsement_no')
                ->where('g.Type', '=', 5) // Use '=' for comparison
                ->where('g.PolicyId', '=', $scheme_id) // Use '=' for comparison
                ->where('gd.MemberID', '=', $member_id) // Use '=' for comparison
                ->get();


            $endorsement_no = $endorsement_no[0]->endorsement_no;


            if ($endorsement_no != null) {
                $endorse_id = $this->britam_db->table('glsuppinfo as g')
                    ->select('g.id')
                    ->where('g.endorsement_no', $endorsement_no)
                    ->first();
                $endorse_id = $endorse_id->id;
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'No extra premium debit notes found for the client.'
                ], 404);
            }
            //exit();
            //SELECT d.reference FROM debitmastinfo d WHERE d.schemeNo = 1096 AND d.endorsement = 2142;

            $results = $this->britam_db->table('debitmastinfo as d')
                ->select('d.reference')
                ->where('d.schemeNo', $scheme_id)
                ->where('d.endorsement', $endorse_id)
                ->get();

            if (sizeof($results) > 0) {
                return response()->json([
                    'success' => true,
                    'message' => 'Extra premium debit note fetched successfully',
                    'count' => count($results),
                    'data' => $results
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'No extra premium debit notes found for the client.'
                ], 404);
            }
        } catch (\Throwable $th) {
            //throw $th;
            return response()->json([
                'success' => false,
                'message' => 'Error fetching data' . $th->getMessage()
            ], 500);
        }
    }

    // GET SCHEME receipts
    public function getSchemesReceipt(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'scheme_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid request data',
                'errors' => $validator->errors()
            ], 400);
        }

        //SELECT g.* FROM glfrontoffice g
        // INNER JOIN glfrontofficedebitsanalysis gd ON gd.glfrontofficeid = g.idd
        // INNER JOIN polschemeinfo p ON p.schemeID = gd.Scheme
        // WHERE p.schemeID = 14;

        try {
            $scheme_id = $request->input('scheme_id');

            $results = $this->britam_db->table('glfrontoffice as g')
                ->join('glfrontofficedebitsanalysis as gd', 'gd.glfrontofficeid', '=', 'g.idd')
                ->join('polschemeinfo as p', 'p.schemeID', '=', 'gd.Scheme')
                ->select('g.*')
                ->where('p.schemeID', $scheme_id)
                ->get();

            if (sizeof($results) > 0) {
                return response()->json([
                    'success' => true,
                    'message' => 'Scheme receipts fetched successfully',
                    'count' => count($results),
                    'data' => $results
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'No receipts found'
                ], 404);
            }
        } catch (\Throwable $th) {
            //throw $th;
            return response()->json([
                'success' => false,
                'message' => 'Error fetching data' . $th->getMessage()
            ], 500);
        }
    }
}
