<?php

namespace App\Http\Controllers\V1\Portal\GroupClient;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DefaultParamsController extends Controller
{
    //
    public function getOccupationClasses()
    {
        $results = $this->britam_db->table('glifeOccupClassInfo')->select("ID", "Industry")->get();

        if ($results != null) {
            return response()->json([
                'success' => true,
                'message' => 'Occupation classes fetched successfully',
                'data' => $results
            ], 200);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'No occupation classes found',
            ], 404);
        }
    }

    public function getNatureOfBusiness()
    {
        $results = $this->britam_db->table('NatureOfBusiness')->select("Id", "Description")->get();

        if ($results != null) {
            return response()->json([
                'success' => true,
                'message' => 'Nature of business fetched successfully',
                'data' => $results
            ], 200);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'No nature of business found',
            ], 404);
        }
    }

    public function getAccessType()
    {
        $results = $this->britam_db->table('SchemeAccessRights')->select("ID", "Description")->get();

        if ($results != null) {
            return response()->json([
                'success' => true,
                'message' => 'Access types fetched successfully',
                'data' => $results
            ], 200);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'No access types found',
            ], 404);
        }
    }

    public function getIdentityTypes()
    {
        $results = $this->britam_db->table("identity_types")->select("id_type", "description")->get();

        if ($results != null) {
            return response()->json([
                'success' => true,
                'message' => 'Access types fetched successfully',
                'data' => $results
            ], 200);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'No access types found',
            ], 404);
        }
    }

    public function getPoliticalStatus()
    {
        $results = $this->britam_db->table('PepStatus')->select('Oid', 'StatusCode')->get();

        if ($results != null) {
            return response()->json([
                'success' => true,
                'message' => 'Access types fetched successfully',
                'data' => $results
            ], 200);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'No access types found',
            ], 404);
        }
    }

    public function getESGStatus()
    {
        $results = $this->britam_db->table('ESGStatus')->select('Oid', 'StatusCode')->get();

        if ($results != null) {
            return response()->json([
                'success' => true,
                'message' => 'Access types fetched successfully',
                'data' => $results
            ], 200);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'No access types found',
            ], 404);
        }
    }

    public function getUfaaDormancyStatus()
    {
        $results = $this->britam_db->table('UFAADormancyStatus')->select('*')->get();

        if ($results != null) {
            return response()->json([
                'success' => true,
                'message' => 'Access types fetched successfully',
                'data' => $results
            ], 200);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'No access types found',
            ], 404);
        }

    }

    public function getCountryInfo()
    {
        $results = $this->britam_db->table('CountryInfo')->select('Code', 'Name')->get();

        if ($results != null) {
            return response()->json([
                'success' => true,
                'message' => 'Access types fetched successfully',
                'data' => $results
            ], 200);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'No access types found',
            ], 404);
        }

    }

    public function getCountyInfo()
    {
        $results = $this->britam_db->table('CountyInfo')->select('Id', 'Description')->get();

        if ($results != null) {
            return response()->json([
                'success' => true,
                'message' => 'Access types fetched successfully',
                'data' => $results
            ], 200);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'No access types found',
            ], 404);
        }

    }

    //get all claim types
    public function getClaimTypes()
    {
        try {
            $results = $this->britam_db->table("claims_types")->select("claim_type", "Description")->get();

            if ($results != null) {
                return response()->json([
                    'success' => true,
                    'message' => 'Claim types fetched successfully',
                    'data' => $results
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'No claim types found',
                ], 404);
            }

        } catch (\Throwable $th) {
            //throw $th;
            return response()->json([
                'success' => false,
                'message' => 'Error fetching claim types' . $th->getMessage()
            ], 500);
        }
    }

    public function getClaimReasons()
    {
        try {
            $results = $this->britam_db->table("ClaimReasons")->select("id", "Description")->where("IsClaimCause", 1)->get();

            if ($results != null) {
                return response()->json([
                    'success' => true,
                    'message' => 'Claim reasons fetched successfully',
                    'data' => $results
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'No claim reasons found',
                ], 404);
            }

        } catch (\Throwable $th) {
            //throw $th;
            return response()->json([
                'success' => false,
                'message' => 'Error fetching claim reasons' . $th->getMessage()
            ], 500);
        }
    }

    //TODO create the - glife_plan_rider_config
    public function getPlanRiderConfig()
    {
        try {
            $results = $this->britam_db->table("glife_plan_rider_config as t1")
            ->join('gliferider_info as t2', 't1.rider_code', '=', 't2.rider_code')
            ->select("t1.*", "t2.description as rider_name")
            ->get();

            if ($results != null) {
                return response()->json([
                    'success' => true,
                    'message' => 'Product Riders fetched successfully',
                    'data' => $results
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'No product Riders found',
                ], 404);
            }

        } catch (\Throwable $th) {
            //throw $th;
            return response()->json([
                'success' => false,
                'message' => 'Error fetching product classes' . $th->getMessage()
            ], 500);
        }
    }

    public function getProductClasses()
    {
        try {
            $results = $this->britam_db->table("glifeclass")->
            select("class_code", "short_desc", "Description","IsGroupLifeCover","IsCreditLifeCover",
            "pen","IsTravelInsurance","IsWelfare","IsActive","PortalDescription","IsForPartnerShip",
            "ShowInPortal")->where("IsActive", 1)->get();

            if ($results != null) {
                return response()->json([
                    'success' => true,
                    'message' => 'Product classes fetched successfully',
                    'data' => $results
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'No product classes found',
                ], 404);
            }

        } catch (\Throwable $th) {
            //throw $th;
            return response()->json([
                'success' => false,
                'message' => 'Error fetching product classes' . $th->getMessage()
            ], 500);
        }
    }
    //Get LoanTypes for GroupLife
    public function getGlLoanTypes()
    {
        try {
            $results = $this->britam_db->table("GlifeLoanTypesinfo")->
            select("*")->get();

            if ($results != null) {
                return response()->json([
                    'success' => true,
                    'message' => 'Loan Types fetched successfully',
                    'data' => $results
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'No Loan Types found',
                ], 404);
            }

        } catch (\Throwable $th) {
            //throw $th;
            return response()->json([
                'success' => false,
                'message' => 'Error fetching product classes' . $th->getMessage()
            ], 500);
        }
    }


    //Get Packages for Partnerships
    public function getProductPackages()
    {
        try {
            $results = $this->britam_db->table("GroupClassPackages")->
            select("*")->where("ShowInPortal", 1)->get();

            if ($results != null) {
                return response()->json([
                    'success' => true,
                    'message' => 'Packages fetched successfully',
                    'data' => $results
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'No Packages found',
                ], 404);
            }

        } catch (\Throwable $th) {
            //throw $th;
            return response()->json([
                'success' => false,
                'message' => 'Error fetching product classes' . $th->getMessage()
            ], 500);
        }
    }

    public function getEndorsementTypes()
    {
        try {
            $results = $this->britam_db->table("glifeEndorsementType")->select("id", "Description")->get();

            if ($results != null) {
                return response()->json([
                    'success' => true,
                    'message' => 'Endorsement types fetched successfully',
                    'data' => $results
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'No endorsement types found',
                ], 404);
            }

        } catch (\Throwable $th) {
            //throw $th;
            return response()->json([
                'success' => false,
                'message' => 'Error fetching endorsement types' . $th->getMessage()
            ], 500);
        }
    }

    public function getClaimCauses()
    {
        try {

            $results = $this->britam_db->table("claimcausesinfo")->select("claim_cause_code", "Description")->get();

            if ($results != null) {
                return response()->json([
                    'success' => true,
                    'message' => 'Claim causes fetched successfully',
                    'data' => $results
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'No claim causes found',
                ], 404);
            }

        } catch (\Throwable $th) {
            //throw $th;
            return response()->json([
                'success' => false,
                'message' => 'Error fetching claim causes' . $th->getMessage()
            ], 500);
        }
    }

    public function getPaymemtModesinfo()
    {
        try {

            $results = $this->britam_db->table("paymentmethodsinfo")->select("Paymethod", "PaymethodDescription")->get();

            $modifiedResults = $results->map(function ($item) {
                $item->PaymethodDescription = str_replace('Britam ', '', $item->PaymethodDescription);
                return $item;
            });

            if ($results != null) {
                return response()->json([
                    'success' => true,
                    'message' => 'Payment modes fetched successfully',
                    'data' => $modifiedResults
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'No payment modes found',
                ], 404);
            }

        } catch (\Throwable $th) {
            //throw $th;
            return response()->json([
                'success' => false,
                'message' => 'Error fetching payment modes' . $th->getMessage()
            ], 500);
        }
    }

    public function getBankCodeInfo()
    {
        //SELECT * FROM bankcodesinfo;
        try {

            $results = $this->britam_db->table("bankcodesinfo")->select("bank_code", "description")->get();

            if ($results != null) {
                return response()->json([
                    'success' => true,
                    'message' => 'Bank codes fetched successfully',
                    'data' => $results
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'No bank codes found',
                ], 404);
            }

        } catch (\Throwable $th) {
            //throw $th;
            return response()->json([
                'success' => false,
                'message' => 'Error fetching bank codes' . $th->getMessage()
            ], 500);
        }
    }

    public function getBankBranchInfo(Request $request)
    {
        //SELECT * FROM bankmasterinfo b WHERE b.bank_code = '01';
        try {
            $bankCode = $request->input('bankCode');

            if (isset($bankCode)) {
                $results = $this->britam_db->table("bankmasterinfo")->select("id", "bankBranchCode", "bankBranchName")->where("bank_code", $bankCode)->get();
            } else {
                $results = $this->britam_db->table("bankmasterinfo")->select("id", "bankBranchCode", "bankBranchName")->get();
            }

            if ($results != null) {
                return response()->json([
                    'success' => true,
                    'message' => 'Bank branches fetched successfully',
                    'data' => $results
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'No bank branches found',
                ], 404);
            }

        } catch (\Throwable $th) {
            //throw $th;
            return response()->json([
                'success' => false,
                'message' => 'Error fetching bank branches' . $th->getMessage()
            ], 500);
        }
    }

    public function getRelationshipTypes()
    {
        try {
            $results = $this->britam_db->table("relationship_mainteinance")->select("code", "description")->get();

            if ($results != null) {
                return response()->json([
                    'success' => true,
                    'message' => 'Relationship types fetched successfully',
                    'data' => $results
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'No relationship types found',
                ], 404);
            }

        } catch (\Throwable $th) {
            //throw $th;
            return response()->json([
                'success' => false,
                'message' => 'Error fetching relationship types' . $th->getMessage()
            ], 500);
        }
    }

    public function getStatuses()
    {
        try {
            $results = $this->britam_db->table("glifestatus")->select("status_code", "Description")->get();

            if ($results != null) {
                return response()->json([
                    'success' => true,
                    'message' => 'Statuses fetched successfully',
                    'data' => $results
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'No statuses found',
                ], 404);
            }

        } catch (\Throwable $th) {
            //throw $th;
            return response()->json([
                'success' => false,
                'message' => 'Error fetching statuses' . $th->getMessage()
            ], 500);
        }
    }

    public function getUnderwritingDocs()
    {
        try {
            $results = $this->britam_db->table("UnderwritingDocTypes")->select("Id", "Description")->get();

            if ($results != null) {
                return response()->json([
                    'success' => true,
                    'message' => 'Underwriting documents fetched successfully',
                    'data' => $results
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'No underwriting documents found',
                ], 404);
            }

        } catch (\Throwable $th) {
            //throw $th;
            return response()->json([
                'success' => false,
                'message' => 'Error fetching underwriting documents' . $th->getMessage()
            ], 500);
        }
    }

    // query glifepaymodeinfo
    // query glifeOccupClassInfo

    public function getPaymentModes()
    {
        try {
            $results = $this->britam_db->table("glifepaymodeinfo")->select("Id", "Description")->get();

            if ($results != null) {
                return response()->json([
                    'success' => true,
                    'message' => 'Payment modes fetched successfully',
                    'data' => $results
                ], 200);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'No payment modes found',
                ], 404);
            }

        } catch (\Throwable $th) {
            //throw $th;
            return response()->json([
                'success' => false,
                'message' => 'Error fetching payment modes' . $th->getMessage()
            ], 500);
        }
    }
}
