<?php

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Azure_AD_AuthController;
use App\Http\Controllers\V1\Integration\CSVController;
use App\Http\Controllers\V1\Integration\DocumentsController;
use App\Http\Controllers\V1\Portal\Brokers\BrokersController;
use App\Http\Controllers\V1\Portal\GroupClient\AuthController;
use App\Http\Controllers\V1\Portal\GroupClient\EmailController;
use App\Http\Controllers\V2\Portal\GroupClient\AuthController2;
use App\Http\Controllers\V1\Portal\GroupClient\ClaimsController;
use App\Http\Controllers\V2\Portal\GroupClient\EmailController2;
use App\Http\Controllers\V1\Portal\GroupClient\ReportsController;
use App\Http\Controllers\V1\Portal\GroupClient\SchemesController;
use App\Http\Controllers\V1\Integration\PaymentAnalysisController;
use App\Http\Controllers\V1\Portal\GroupClient\MedicalsController;
use App\Http\Controllers\V1\Portal\GroupClient\EnquiriesController;
use App\Http\Controllers\V1\Portal\GroupClient\QuotationController;
use App\Http\Controllers\V1\Portal\GroupClient\GroupClientController;
use App\Http\Controllers\V1\Portal\GroupClient\DefaultParamsController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// login from the azure ad auth
Route::post('/azure-ad-token-request', [Azure_AD_AuthController::class, 'azure_ad_token_request']);              
Route::post('/getReportsLaravel', [ReportsController::class, 'getReportsLaravel']);
Route::get('/send-gls-to-staging', [CSVController::class, 'GLToStagingAutomated']);


Route::group(
    [
        'prefix' => 'v1',
        'middleware' => ['client']
        //'middleware' => ['azure.ad.auth']
    ],
    function () {
        Route::get('/test-database-connection', [Controller::class, 'testDatabaseConnection']);
        Route::get('/send-payment', [PaymentAnalysisController::class, 'sendPayment']);
        Route::post('/sendDocument', [DocumentsController::class, 'sendDocument']);
        Route::post('/getDocument', [DocumentsController::class, 'getDocument']);
        Route::get('/getGLLifetable', [CSVController::class, 'getGLLifetable']);
        Route::get('/reverseGetGLLifetable', [CSVController::class, 'reverseGetGLLifetable']);
    }
);

Route::group(
    [
        'prefix' => 'v1-gc-portal',
        'middleware' => ['client']
        //'middleware' => ['azure.ad.auth']
    ],
    function () {

        //defaults - 
        Route::get('/occupation-classes', [DefaultParamsController::class, 'getOccupationClasses']);
        Route::get('/nature-of-business', [DefaultParamsController::class, 'getNatureOfBusiness']);
        Route::get('/access-types', [DefaultParamsController::class, 'getAccessType']);
        Route::get('/identity-types', [DefaultParamsController::class, 'getIdentityTypes']);
        Route::get('/political-status', [DefaultParamsController::class, 'getPoliticalStatus']);
        Route::get('/esg-status', [DefaultParamsController::class, 'getESGStatus']);
        Route::get('/ufaa-dormancy-status', [DefaultParamsController::class, 'getUfaaDormancyStatus']);
        Route::get('/slams-preset-countries', [DefaultParamsController::class, 'getCountryInfo']);
        Route::get('/counties', [DefaultParamsController::class, 'getCountyInfo']);
        Route::get('/claim-types', [DefaultParamsController::class, 'getClaimTypes']);
        Route::get('/product-classes', [DefaultParamsController::class, 'getProductClasses']);
        Route::get('/product-rider-classes', [DefaultParamsController::class, 'getPlanRiderConfig']);
        Route::get('/product-packages-classes', [DefaultParamsController::class, 'getProductPackages']);
        //
        //Route::get('/product-classes', [DefaultParamsController::class, 'getPlanRiderConfig']);
        Route::get('/endorsement-types', [DefaultParamsController::class, 'getEndorsementTypes']);
        Route::get('/claim-causes', [DefaultParamsController::class, 'getClaimCauses']);
        Route::get('/get-payment-methods', [DefaultParamsController::class, 'getPaymemtModesinfo']);
        Route::get('/get-banks', [DefaultParamsController::class, 'getBankCodeInfo']);
        Route::get('/get-bank-branches-codes', [DefaultParamsController::class, 'getBankBranchInfo']);
        Route::get('/get-relationships', [DefaultParamsController::class, 'getRelationshipTypes']);
        Route::get('/get-statuses', [DefaultParamsController::class, 'getStatuses']);
        Route::get('/get-claim-reasons', [DefaultParamsController::class, 'getClaimReasons']);
        Route::get('/get-underwriting-doc-types', [DefaultParamsController::class, 'getUnderwritingDocs']);
        Route::get('/get-payment-modes', [DefaultParamsController::class, 'getPaymentModes']);
        Route::get('/get-loan-types', [DefaultParamsController::class, 'getGlLoanTypes']);

        //client
        Route::get('/getGroupClients', [GroupClientController::class, 'getGroupClients']);
        Route::post('/setGroupClient', [GroupClientController::class, 'setGroupClient']);
        Route::post('/updateGroupClient', [GroupClientController::class, 'updateGroupClient']);

        //contact person
        Route::get('/getGCContactPersons', [GroupClientController::class, 'getGCContactPersons']);
        Route::post('/setGCContactPersons', [GroupClientController::class, 'setGCContactPersons']);
        Route::post('/updateGCContactPersons', [GroupClientController::class, 'updateGCContactPersons']);
        Route::get('/contact-allowed-person-schemes', [GroupClientController::class, 'getGCContactPersonSchemes']);

        //directors
        Route::get('/getGCDirectors', [GroupClientController::class, 'getGCDirectors']);
        Route::post('/setGCDirectors', [GroupClientController::class, 'setGCDirectors']);
        Route::post('/updateGCDirectors', [GroupClientController::class, 'updateGCDirectors']);

        //bank details
        Route::get('/getGCBankDetails', [GroupClientController::class, 'getGCBankDetails']);
        Route::post('/setGCBankDetails', [GroupClientController::class, 'setGCBankDetails']);
        Route::post('/updateGCBankDetails', [GroupClientController::class, 'updateGCBankDetails']);

        // debit/credit notes
        Route::get('/client-debit-notes', [GroupClientController::class, 'getDebitNotes']);
        Route::get('/client-credit-notes', [GroupClientController::class, 'getCreditNotes']);


        //brokers
        Route::get('/all-brokers', [BrokersController::class, 'getBrokers']);
        Route::get('/schemes-under-broker', [BrokersController::class, 'getSchemesUnderBroker']);
        Route::get('/broker-commissions', [BrokersController::class, 'getBrokersCommission']);
        Route::get('/broker-info', [BrokersController::class, 'getBrokerInfo']);
        Route::get('/broker-contact-persons', [BrokersController::class, 'getBrokerContactPersons']);


        //claims 
        Route::get('/search-claimant', [ClaimsController::class, 'searchClaimantByName']);
        Route::get('/all-claims-on-scheme', [ClaimsController::class, 'getAllClaimsUnderScheme']);
        Route::get('/get-required-docs', [ClaimsController::class, 'getRequiredDocuments']);
        Route::post('/submit-claim-request', [ClaimsController::class, 'submitClaimRequest']);
        Route::post('/submit-claim-docs', [ClaimsController::class, 'uploadDocumentsToClaimRequest']);
        Route::get('/get-claim-requests', [ClaimsController::class, 'getClaimRequests']);
        Route::get('/get-scheme-benefit', [ClaimsController::class, 'getSchemeBenefit']);
        Route::get('/claim-status', [ClaimsController::class, 'getClaimStatus']);
        Route::post('/approve-claim', [ClaimsController::class, 'approveClaimRequest']);

        //schemes
        Route::get('/getClientSchemes', [SchemesController::class, 'getClientSchemes']);
        Route::get('/getMembersPerScheme', [SchemesController::class, 'getMembersPerScheme']);
        Route::get('/search-members', [SchemesController::class, 'searchMemberBySurname']);
        Route::get('/search-credit-life-members', [SchemesController::class, 'search_by_name_and_loan_number']);
        Route::get('/scheme-riders', [SchemesController::class, 'getSchemeRiders']);
        Route::get('/get-member-dependants', [SchemesController::class, 'getDepedantsAndBenefUnderMember']);
        Route::get('/get-dependants-on-member', [SchemesController::class, 'getDependantsUnderMember']);
        Route::get('/get-member-benef', [SchemesController::class, 'getBeneficiariesUndMember']);
        Route::post('/policy-cover-periods', [SchemesController::class, 'getPolicyCoverPeriods']);
        Route::get('/get-members-xtraP-debit-notes', [SchemesController::class, 'getRExtraPremiumDebitNotes']);
        Route::get('/get-scheme-receipts', [SchemesController::class, 'getSchemesReceipt']);
        Route::get('/scheme-categories', [SchemesController::class, 'getSchemeCategories']);


        //endorsements
        Route::post('/request-endorsements', [SchemesController::class, 'endorsementRequest']);
        Route::post('/add-dependants-to-endorsements', [SchemesController::class, 'addDependantsToEndosementReqMembers']);
        Route::get('/get-endorsement-requests', [SchemesController::class, 'getRequestedEndorsements']);
        Route::post('/set-endorsement-request', [SchemesController::class, 'setEndorsementRequest']);
        Route::post('/set-endorsement-members', [SchemesController::class, 'setEndorsementMembers']);
        Route::post('/client-with-multiple-endo-members', [SchemesController::class, 'uploadEndoresementExcelDocument']);
        Route::post('/credit-life-endorsement-request', [SchemesController::class, 'credit_life_endorsement_request']);
        Route::post('/get-endorsements-processing-results', [ReportsController::class, 'getEndorsementProcessingResults']);
        Route::post('/get-claims-processing-results', [ReportsController::class, 'getClaimsProcessingResults']);
        Route::post('/post-debits-to-gl', [ReportsController::class, 'postRaisedDebitsToGL']);
        Route::post('/recalculate-scheme-balance', [ReportsController::class, 'recalculateSchemeBalance']);

        //medicals
        Route::post('/members-going-for-medicals', [MedicalsController::class, 'getMembersToGoForMedicals']);
        Route::post('/members-who-have-done-medicals', [MedicalsController::class, 'getMembersWhoHaveDoneMedicals']);
        Route::post('/members-whose-meds-uw', [MedicalsController::class, 'getMemberswhoseMediacalsAreUnderwritten']);
        Route::get('/uw-decisions', [MedicalsController::class, 'getUWDecisions']);


        //email settings
        Route::post('/send-otp', [EmailController::class, 'sendOTP']);
        //Route::post('/confirm-otp', [EmailController::class, 'verifyOTP']);
        Route::post('/send-otp-using-policy-number', [EmailController::class, 'sendOTPusingPolicyNumber']);


        //enquiries
        Route::post('/submit-enquiry', [EnquiriesController::class, 'submitEnquiry']);
        Route::get('/enquiries-under-client', [EnquiriesController::class, 'getEnquiryiesUnderClient']);
        Route::get('/get-enquiry-thread', [EnquiriesController::class, 'getEnquiryThread']);
        Route::get('/get-predefined-subjects', [EnquiriesController::class, 'getPredefinedSubjects']);
        Route::post('/reply-to-response', [EnquiriesController::class, 'replyToResponse']);

        Route::post('/confirm-otp', [EmailController2::class, 'verifyOTP']);

        // quotation
        Route::post('/create-quotation', [QuotationController::class, 'createGroupQuotation']);
        Route::get('/view-quotation', [QuotationController::class, 'viewGroupQuotation']);
    }
);

Route::group(
    [
        'prefix' => 'v2-gc-portal',
        'middleware' => ['client']
        //'middleware' => ['azure.ad.auth']
    ],
    function () {

        //get current user
        Route::get('/get-current-cp-user', [AuthController2::class, 'GetCurrentContactPersons']);
        Route::get('/get-current-user', [AuthController2::class, 'GetCurrentContactPersonsUsingToken']);
        Route::post('/login-as-cp', [AuthController2::class, 'LoginAsContactPersons']);
        Route::post('/create-cp-password', [AuthController2::class, 'CreateContactPersonsPassword']);
        Route::post('/forgot-cp-password', [AuthController2::class, 'ForgotCPPassword']);
        Route::post('/logout-cp', [AuthController2::class, 'LogoutAsContactPersons']);
        //emails
        Route::post('/send-otp-for-password-creation', [EmailController2::class, 'sendOTPusingPolicyNumber']);
        Route::post('/confirm-otp-for-password-creation', [EmailController2::class, 'verifyOTP']);
    }

);

Route::get('/auth/user', [AuthController2::class, 'user'])->middleware('auth:sanctum');