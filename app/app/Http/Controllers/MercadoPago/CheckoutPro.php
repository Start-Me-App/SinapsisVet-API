<?php

declare(strict_types=1);

namespace App\Http\Controllers\MercadoPago;
use App\Models\ResponseMercadoPago;

use Psr\Http\Message\{
    ResponseInterface as Response,
    ServerRequestInterface as Request
};

use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\Exceptions\MPApiException;

use App\Support\TokenManager;

final class CheckoutPro extends MercadoPago
{

    /**
     * Process payment from MercadoPago as Checkout Pro.
     *
     * @param Request $request
     * @return void
     */
    public function processPreference($items,$payer,$external_reference)
    {

        $preference = self::_createPreference($items,$payer,$external_reference);

        if(is_array($preference)){
            if(isset($preference['error_message'])){
                var_dump($preference['error_message']);
                    return null;
                }
        }

        return $preference;
    }

    /**
     * Return id of preference from MercadoPago.
     *
     * @param Request $request
     * @return array
     */
    private function _createPreference($total,$payer,$external_reference)
    {
        try {

            $conditionOrderId = false;

            $existPreference = self::_searchPreference((int)$external_reference);

            if( ($existPreference != null) && (!empty($existPreference->preference_id))) {
                return  ['preference_id' => $existPreference->preference_id];
            }

            if (isset($existPreference->order_id) && ($existPreference->preference_id == '' || is_null($existPreference))) {
                $conditionOrderId = $existPreference->order_id;
            }

           $preference = new PreferenceClient();

              // Fill the data about the product(s) being pruchased
            $product = [
                "title" => "Cursos y Talleres",
                "description" => "Cursos y Talleres",
                "currency_id" => "ARS",
                "quantity" => 1,
                "unit_price" => $total
            ];

            $product_array[] = $product;

            $accessToken = TokenManager::getTokenFromRequest();
            $user = TokenManager::getUserFromToken($accessToken);
            $payer = array(
                "name" => $user->name,
                "surname" => $user->lastname,
                "email" => $user->email,
                "user_id" => $user->id
            );

            // Create the request object to be sent to the API when the preference is created
            $request = self::_createPreferenceRequest($product_array, $payer,$external_reference);

            // Instantiate a new Preference Client
            $client = new PreferenceClient();

            try {
                // Send the request that will create the new preference for user's checkout flow
                $preference = $client->create($request);
            } catch (MPApiException $error) {
                // Here you might return whatever your app needs.
                // We are returning null here as an example.
                var_dump('error');
                var_dump($error);
                return ['error_message' => $error->getMessage()];
            }

            if($conditionOrderId == false){
                # Save in the database
                $res_mercadopago  = new ResponseMercadoPago();
                $res_mercadopago->user_id = (int) $user->id;
                $res_mercadopago->order_id  = (int) $external_reference;
                $res_mercadopago->preference_id = (string) $preference->id;
                $res_mercadopago->save();
            }else{
               #find response mercadopago by id
               $existPreference->preference_id = (string) $preference->id;
               $existPreference->save();
            }
            return $preference;
        } catch (\Exception $exception) {

            return ['error_message' => $exception->getMessage()];
        }
    }

    /**
     * Get Id Preferences by OrderId if exist.
     *
     * @param Request $request
     */
    private function _searchPreference($orderId)
    {
        #search preference in database
        $preference = ResponseMercadoPago::where('order_id', $orderId)->first();
        return (isset($preference->order_id) && isset($preference->preference_id)) ? $preference : NULL;
    }

    /**
     * Contains the payment data to process checkout Pro.
     *
     * @param Request $request
     * @return array
     */
    private function _getAttributePaymentPro($items)
    {
        $formData = (array) $items;

        $required_structure = [
            "title"      => "",
            "quantity"   => "",
            "unit_price" => ""
        ];

        # validate array structure
        $data = array_diff_key($required_structure, $formData);

        if(count($data) > 0){
            throw new \Exception("Faltan datos para procesar el pago");
        }

        return array_intersect_key($formData, $required_structure);
    }


    private function _createPreferenceRequest($items, $payer,$external_reference)
    {     
        $paymentMethods = [
            "excluded_payment_methods" => [],
            "installments" => 12,
            "default_installments" => 1
        ];

        $backUrls = array(
            'success' => $_ENV['URL_WEB'] . 'mercadopago/success',
            'failure' => $_ENV['URL_WEB'] . 'mercadopago/failed'
        );

        $request = [
            "items" => $items,
            "payer" => $payer,
            "payment_methods" => $paymentMethods,
            "back_urls" => $backUrls,
            "statement_descriptor" => "NAME_DISPLAYED_IN_USER_BILLING",
            "external_reference" => $external_reference,
            "expires" => false,
            "auto_return" => 'approved',
            "notification_url" => $this->notification_url
        ];

        return $request;
    }
}
