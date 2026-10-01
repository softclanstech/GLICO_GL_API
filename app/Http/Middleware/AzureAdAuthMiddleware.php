<?php

namespace App\Http\Middleware;

use Closure;
use stdClass;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class AzureAdAuthMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Check if the token is present in the request
        if ($request->bearerToken()) {

            //get token 
            $token = $request->bearerToken();

            //print_r($token);
            $http = new \GuzzleHttp\Client;


            $headers = [
                'Ocp-Apim-Subscription-Key' => config('services.azure_ad.subscription_key'),
                'Authorization' => 'Bearer ' . $token,
            ];


            $response = $http->get('https://brtgw.britam.com/api/auth/verify/', [
                'headers' => $headers,
            ]);

            if ($response->getStatusCode() == 200) {

                $response = json_decode((string) $response->getBody(), true);

                if ($response['status'] == 'Success' && $response['message'] == 'Valid User') {

                    $roles = $response['data']['roles'];

                    //check if the user has the required role to access the resource
                    if (in_array('RegisterClient', $roles) || in_array('VerifyAgent', $roles)) {

                        return $next($request);

                    } else {

                        return response()->json(['error' => 'Unauthorized. User does not have the required role to access the resource.'], 401);
                    }
                } else {

                    return response()->json(['error' => 'Unauthorized. User is not valid.'], 401);
                }
            } else {

                return $response;
            }

        } else {

            return response()->json(['error' => 'Unauthorized. Token not provided.'], 401);
        }

    }
}
