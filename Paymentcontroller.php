<?php

namespace App\Http\Controllers\v1;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\View;
use App\User;
use Auth;
use DB;
use Crypt;
use Mail;
use DateTime;
use File;
use Response;
use Carbon\Carbon;
use App\v1\WebserviceModel;
use App\notification;
use Stripe\Stripe;
use Stripe\Customer;
use Stripe\Charge;
use Stripe\Token;

class Paymentcontroller extends Controller
{

 private $required_field_error = "Please fill all fields!";
    

    public function __construct(Request $req){

    $this->webserviceModel = new WebserviceModel;

    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: PUT, GET, POST");
    header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");

    $version = $req->header('appversion');
    $device_type=$req->header('devicetype');


     if($version < 1.0 && $device_type == 'A'){

      echo json_encode(array('status' => 426, 'message' => 'An updated version  of Touchmassage is available please update unless your application will not work.'));
      die;

    }elseif($version < 1.0 && $device_type == 'I'){

      echo json_encode(array('status' => 426, 'message' => 'An updated version of Touchmassage is available please update unless your application will not work.'));
      die;

    }

    } //end of constructor

    /***************************************/
    /* FUNCTION FOR CHECK TOKEN EXPIRED OR NOT
    /***************************************/

    function GetCheckToken($user,$login_token)
    {
       $user = DB::table('users')->where('id','=',$user)->where('token','=', $login_token)->select('id')->first();
      
        if (empty($user)) {
          
           return false;
                
         }else{
             return true;
         }
    }  

  /**************************************/
              /* Card Save */
  /***************************************/
  function card_save(Request $req){
  

   $rememberToken = $req->header('token');
          $checktoken= $this->GetCheckToken($req->input('user_id'),$rememberToken);
               if(!$checktoken){
                   return response(array('message'=>'Wrong token entered!.Please try again'), UNAUTHORIZED)->header('Content-Type', 'application/json');
               }

    $expmnt_year = $req->input('expiry_date');  // 2020-01 format 
    $year_month = explode("/", $expmnt_year);
    
    $exp_month=$year_month[0];
    $exp_year=$year_month[1];
  
       
            $user_id     = $req->input('user_id');
            $card_number = $req->input('card_number');
            $exp_month   = $exp_month;
            $exp_year    = $exp_year;
            $cvv_number  = $req->input('cvv_number');
            $cardHoldername  = $req->input('cardholder_name');
            $usert_ype  = $req->input('user_type');

        $customers_id = DB::table('customer')->where('user_id',$user_id)->select('customer_id')->first();
 
        if($customers_id){
          $customer_id = $customers_id->customer_id;
            $customer_id = $customer_id;
        }else{
         
            $customer_data = $this->create_customer($req->input('user_id'));
            if(@$customer_data['message'] && $customer_data['message'] !== '' && !@$customer_data['Checkadded']){
        
              return response($customer_data, NOT_ACCEPTABLE)->header('Content-Type', 'application/json');
            }
            $customer_id=$customer_data['data'];
        }

       $saveCard = $this->token_create($card_number,$exp_year,$exp_month,$cvv_number,$user_id,$customer_id,$cardHoldername,$usert_ype);
    
        if(@$saveCard['message'] && $saveCard['message'] !== '' && !@$saveCard['Checkadded']){
          
              return response($saveCard, NOT_ACCEPTABLE)->header('Content-Type', 'application/json');
            }

        if($saveCard){
           
           return response($saveCard, SUCCESS)->header('Content-Type', 'application/json');
        }
  }

    /************************************/
    /* create_customer(stripe) */
   /*************************************/
   function create_customer($user_id){

      $getemail=DB::table('users')->where('id','=',$user_id)->select('email')->first();
        
      if(empty($getemail->email)){
        echo json_encode(array('status' => 204, 'message' =>'Email not found'));
      die;

    }try{

      
      \Stripe\Stripe::setApiKey(env('STRIPE_SECRET'));
      $customers = \Stripe\Customer::create(array("email" => $getemail->email));

      $data = array(
            'user_id' => $user_id,
            'customer_id' => $customers->id);

      DB::table('customer')->insert($data);
     return array('message' => 'customer create successfully','data' =>$customers->id,'Checkadded'=>'true');
    
    } catch(\Stripe\Error\Card $e) {
       
        $body = $e->getJsonBody();   // Since it's a decline, \Stripe\Error\Card will be caught
        $err  = $body['error'];

        $results['status'] = $e->getHttpStatus();
        $results['type']   = $err['type'];
        $results['code']   = $err['code'];
       // param is '' in this case
        $results['param']  = $err['param'];
        $results['message'] = $err['message'];

        return array('message' =>$results['message'],'data' =>$results);
        
    
    } catch (\Stripe\Error\RateLimit $e) {

        // Too many requests made to the API too quickly

        $body = $e->getJsonBody();
        $err  = $body['error'];

        $results['message'] = 'Too many requests made to the API too quickly';
       return array('message' => $results['message'], 'data' => $err);
    
    } catch (\Stripe\Error\InvalidRequest $e) {

      // Invalid parameters were supplied to Stripe's API
      $body = $e->getJsonBody();
        $err  = $body['error'];

      $results['message']  = "Invalid parameters were supplied to Stripe's API";
      return array('message' => $results['message'], 'data' => $err);

    } catch (\Stripe\Error\Authentication $e) {

      // Authentication with Stripe's API failed
      // (maybe you changed API keys recently)
      $body = $e->getJsonBody();
        $err  = $body['error'];

      return array('message' => "Authentication with Stripe's API failed (maybe you changed API keys recently)", 'data' => $err);  
    
    } catch (\Stripe\Error\ApiConnection $e) {
      // Network communication with Stripe failed

      $body = $e->getJsonBody();
        $err  = $body['error'];

      $results['message']  =  "Network communication with Stripe failed";
        return array('message' => $results['message'], 'data' => $err);

    } catch (\Stripe\Error\Base $e) {
      // Display a very generic error to the user, and maybe send
      // yourself an email

      $body = $e->getJsonBody();
        $err  = $body['error'];

      $results['message']  =  "Error";
      return array('message' => $results['message'], 'data' => $err);
    
    } catch (Exception $e) {
      // Something else happened, completely unrelated to Stripe
      $results['message']  = "Something else happened, completely unrelated to Stripe";
      
      $body = $e->getJsonBody();
        $err  = $body['error'];

      return array('message' => $results['message'], 'data' => $err); 
    }
  }

   /************************************/
    /* token_create(stripe) */
    /*************************************/

  function token_create($card_number,$expiry_year,$exp_month,$cvv_number,$user_id,$customer_id=null,$cardHoldername,$usert_ype){

    try{
        
        
    \Stripe\Stripe::setApiKey(env('STRIPE_SECRET'));
           
    $token = \Stripe\Token::create(array(
            "card"        => array(
            "number"    => $card_number,
            "exp_month"   => $exp_month,
            "exp_year"    => $expiry_year,
            "cvc"         => $cvv_number)));

    if(empty($customer_id)){

            return $token->id;
    }else{
            
    $customer = \Stripe\Customer::retrieve($customer_id);
        $cardDetails = $customer->sources->create(array("source" =>$token->id));
    
    // check card alread save or not on the bases of fingerprint
      $cardfinger=$cardDetails->fingerprint;

      $fingerprints=DB::table('add_cards')->where('user_id',$user_id)->where('fingerprint',$cardfinger)->select('fingerprint')->first();
     if($fingerprints){

       if($cardDetails->fingerprint == $fingerprints->fingerprint){
       return array('message' =>'Card Already Exists');
    }
  }else{

      $cardDetaill = array(
          'user_id'     => $user_id,
          'card_id'     => $cardDetails->id,
          'object'    => $cardDetails->object,
          'brand'     => $cardDetails->brand,
          'country'     => $cardDetails->country,
          'customer'    => $cardDetails->customer,
          'exp_month'   => $cardDetails->exp_month,
          'exp_year'    => $cardDetails->exp_year,
          'fingerprint' => $cardDetails->fingerprint,
          'last4'       => $cardDetails->last4,
          'usert_ype'   => $usert_ype,
          'cardholder_name'   => $cardHoldername
          );

    
           DB::table('add_cards')->insert($cardDetaill);
           return array('message' => 'Card add successfully','data' =>$cardDetails->customer,'Checkadded'=>'true');
          
          }
      }
        } catch(\Stripe\Error\Card $e) {
        // Since it's a decline, \Stripe\Error\Card will be caught
        $body = $e->getJsonBody();
        $err  = $body['error'];

        $results['status'] = $e->getHttpStatus();
        $results['type']   = $err['type'];
        $results['code']   = $err['code'];
      // param is '' in this case
        $results['param']  = $err['param'];
        $results['message'] = $err['message'];

        return array('message' => $results['message'],'data' =>$results);
    
    } catch (\Stripe\Error\RateLimit $e) {

        // Too many requests made to the API too quickly

        $body = $e->getJsonBody();
        $err  = $body['error'];

        $results['message'] = 'Too many requests made to the API too quickly';
      return array('message' => $results['message'], 'data' => $err);
    
    } catch (\Stripe\Error\InvalidRequest $e) {

      // Invalid parameters were supplied to Stripe's API
      $body = $e->getJsonBody();
        $err  = $body['error'];

      $results['message']  = "Invalid parameters were supplied to Stripe's API";
      return array('message' => $results['message'], 'data' => $err);

    } catch (\Stripe\Error\Authentication $e) {

      // Authentication with Stripe's API failed
      // (maybe you changed API keys recently)
      $body = $e->getJsonBody();
        $err  = $body['error'];

      return array('message' => "Authentication with Stripe's API failed (maybe you changed API keys recently)", 'data' => $err);  
    
    } catch (\Stripe\Error\ApiConnection $e) {
      // Network communication with Stripe failed

      $body = $e->getJsonBody();
        $err  = $body['error'];

      $results['message']  =  "Network communication with Stripe failed";
       return array('message' => $results['message'], 'data' => $err);

    } catch (\Stripe\Error\Base $e) {
      // Display a very generic error to the user, and maybe send
      // yourself an email

      $body = $e->getJsonBody();
        $err  = $body['error'];

      $results['message']  =  "Error";
     return array('message' => $results['message'], 'data' => $err);
    
    } catch (Exception $e) {
      // Something else happened, completely unrelated to Stripe
      $results['message']  = "Something else happened, completely unrelated to Stripe";
      
      $body = $e->getJsonBody();
        $err  = $body['error'];

      return array('message' => $results['message'], 'data' => $err);
    }

  }

/*************************************************/
                 /*card list*/
/*************************************************/


	public function cards_list($user_id){  
     
        $cust_id=DB::table('customer')->where('user_id',$user_id)->select('customer_id')->first();
	    $cusromer_id=$cust_id->customer_id;
	
		try {
		
            \Stripe\Stripe::setApiKey(env('STRIPE_SECRET'));
            $carddetail_list =    \Stripe\Customer::retrieve($cusromer_id)->sources->all(array(
            'object' => 'card'));

   
        $card_list_data=array();
        foreach ($carddetail_list->data as $k => $val){
	           $data['card_id'] = $val->id;
	           $data['customer'] = $val->customer;
	           $data['exp_month'] = $val->exp_month;
	           $data['exp_year'] = $val->exp_year;
	           $data['last4'] = $val->last4;
	           $data['brand'] = $val->brand;
               $card_list_data[]=$data;
            }
      	
	  		return response(array('message'=>'card list','data' =>$card_list_data), SUCCESS)->header('Content-Type', 'application/json'); 

 
	  	} catch(\Stripe\Error\Card $e) {
		  	// Since it's a decline, \Stripe\Error\Card will be caught
		  	$body = $e->getJsonBody();
		  	$err  = $body['error'];

		  	$results['status'] = $e->getHttpStatus();
		  	$results['type']   = $err['type'];
		  	$results['code']   = $err['code'];
		 	 // param is '' in this case
		  	$results['param']  = $err['param'];
		  	$results['message'] = $err['message'];

		  	return array('message' =>$results['message'],'data' =>$results);
		
		} catch (\Stripe\Error\RateLimit $e) {

		  	// Too many requests made to the API too quickly

		  	$body = $e->getJsonBody();
		  	$err  = $body['error'];

		  	$results['message'] = 'Too many requests made to the API too quickly';
			return array('message' => $results['message'], 'data' => $err);
		
		} catch (\Stripe\Error\InvalidRequest $e) {

		  // Invalid parameters were supplied to Stripe's API
			$body = $e->getJsonBody();
		  	$err  = $body['error'];

			$results['message']	 = "Invalid parameters were supplied to Stripe's API";
		return array('message' => $results['message'], 'data' => $err);

		} catch (\Stripe\Error\Authentication $e) {

		  // Authentication with Stripe's API failed
		  // (maybe you changed API keys recently)
			$body = $e->getJsonBody();
		  	$err  = $body['error'];

			return array('message' => "Authentication with Stripe's API failed (maybe you changed API keys recently)", 'data' => $err);	
		
		} catch (\Stripe\Error\ApiConnection $e) {
		  // Network communication with Stripe failed

			$body = $e->getJsonBody();
		  	$err  = $body['error'];

			$results['message']	 =  "Network communication with Stripe failed";
		    return array('message' => $results['message'], 'data' => $err);

		} catch (\Stripe\Error\Base $e) {
		  // Display a very generic error to the user, and maybe send
		  // yourself an email

			$body = $e->getJsonBody();
		  	$err  = $body['error'];

			$results['message']	 =  "Error";
			return array('message' => $results['message'], 'data' => $err);
		
		} catch (Exception $e) {
		  // Something else happened, completely unrelated to Stripe
			$results['message']	 = "Something else happened, completely unrelated to Stripe";
			
			$body = $e->getJsonBody();
		  	$err  = $body['error'];

			return array('message' => $results['message'], 'data' => $err);
		}
	}

/*************************************/
/* card delete */
/*************************************/
  public function cards_delete(Request $req){  
     $user_id=$req->input('user_id');
     $card_id=$req->input('card_id');
        
      $cust_id=DB::table('customer')->where('user_id',$user_id)->select('customer_id')->first();
      $cusromer_id=$cust_id->customer_id;
  
    try {

            \Stripe\Stripe::setApiKey(env('STRIPE_SECRET'));
            $customer = \Stripe\Customer::retrieve($cusromer_id);
            $customer->sources->retrieve($card_id)->delete();


  
        
      echo json_encode(array('status' => 200, 'message' =>'card delete Successfully')); 
        

 
      } catch(\Stripe\Error\Card $e) {
        // Since it's a decline, \Stripe\Error\Card will be caught
        $body = $e->getJsonBody();
        $err  = $body['error'];

        $results['status'] = $e->getHttpStatus();
        $results['type']   = $err['type'];
        $results['code']   = $err['code'];
       // param is '' in this case
        $results['param']  = $err['param'];
        $results['message'] = $err['message'];

        echo json_encode(array('status' => 204, 'message' =>$results['message'],'data' =>$results));  die;
    
    } catch (\Stripe\Error\RateLimit $e) {

        // Too many requests made to the API too quickly

        $body = $e->getJsonBody();
        $err  = $body['error'];

        $results['message'] = 'Too many requests made to the API too quickly';
      echo json_encode(array('status' => 204, 'message' => $results['message'], 'data' => $err)); die;
    
    } catch (\Stripe\Error\InvalidRequest $e) {

      // Invalid parameters were supplied to Stripe's API
      $body = $e->getJsonBody();
        $err  = $body['error'];

      $results['message']  = "Invalid parameters were supplied to Stripe's API";
      echo json_encode(array('status' => 204, 'message' => $results['message'], 'data' => $err));die;

    } catch (\Stripe\Error\Authentication $e) {

      // Authentication with Stripe's API failed
      // (maybe you changed API keys recently)
      $body = $e->getJsonBody();
        $err  = $body['error'];

      echo  json_encode(array('status' => 204, 'message' => "Authentication with Stripe's API failed (maybe you changed API keys recently)", 'data' => $err));die;  
    
    } catch (\Stripe\Error\ApiConnection $e) {
      // Network communication with Stripe failed

      $body = $e->getJsonBody();
        $err  = $body['error'];

      $results['message']  =  "Network communication with Stripe failed";
        echo json_encode(array('status' => 204, 'message' => $results['message'], 'data' => $err));die;

    } catch (\Stripe\Error\Base $e) {
      // Display a very generic error to the user, and maybe send
      // yourself an email

      $body = $e->getJsonBody();
        $err  = $body['error'];

      $results['message']  =  "Error";
      echo json_encode(array('status' => 204, 'message' => $results['message'], 'data' => $err)); die;
    
    } catch (Exception $e) {
      // Something else happened, completely unrelated to Stripe
      $results['message']  = "Something else happened, completely unrelated to Stripe";
      
      $body = $e->getJsonBody();
        $err  = $body['error'];

      echo json_encode(array('status' => 204, 'message' => $results['message'], 'data' => $err));die;
    }
  }








/**************************/
/* Payment */
/**************************/
public function payment(Request $req){
       
       $card_id=$req->input('card_id');
       $user_id=$req->input('user_id');
       $amount=$req->input('price');
       $services_id=$req->input('services_id');

    $rememberToken = $req->header('token');
    $this->GetCheckToken($req->input('user_id'),$rememberToken);

     $card_customer_id=DB::table('add_cards')->where('user_id',$user_id)->where('card_id',$card_id)->select('customer')->first();
  

	  try {		
			\Stripe\Stripe::setApiKey(env('STRIPE_SECRET'));
           
            if($card_id){
				$source = "customer"; // obtained with Stripe.js
			}else{
				$source = "source";
			}

		$charge = \Stripe\Charge::create(array(
				"amount" => $amount*100,
				"currency" => "usd",
				$source 	=> $card_customer_id->customer,						
				"description" => "Charge for TouchMasage"
				));
    
		$payment = array(
             
                'charge_id' => $charge->id,
                'object' => $charge->object,
                'created' => $charge->created,
                'currency' => $charge->currency,
                'customer' => $charge->customer,
                'stripe_transaction_id' => $charge->balance_transaction,
                'card_id' => $charge->source->id,
				'fingerprint' => $charge->source->fingerprint,
				'funding' => $charge->source->funding,
				'last4' => $charge->source->last4,
				'exp_month' => $charge->source->exp_month,
				'exp_year' => $charge->source->exp_year,
				'brand' => $charge->source->brand,
	            'user_id' => $user_id,
	            'services_id' => $services_id,
	            'amount' => $amount
	            );

        $payment_result=DB::table('payment')->insertGetId($payment);

        if($payment_result){
          $data = array(
              'user_id' =>$req->input('user_id'),
              'services_id' =>$req->input('services_id'),
              'therapist_gender' =>$req->input('therapist_gender'),
              'massage_length' =>$req->input('massage_length'),
              'start_date' =>$req->input('start_date'),
              'start_time' =>$req->input('start_time'),
              'street' =>$req->input('street'),
              'city' =>$req->input('city'),
              'zip' =>$req->input('zip'),
              'state' =>$req->input('state'),
              'parking_instruction' =>$req->input('parking_instruction'),
              'price' =>$req->input('price'),
              'offer_name' =>$req->input('offer_name'),
              'offer_price' =>$req->input('offer_price'),
              'job_forword' =>'NA',
              'job_status' =>'N');

          $job_id=DB::table('post_services')->insertGetId($data);

          DB::table('payment')->where('id',$payment_result)->update(['job_id'=>$job_id]);
 
        if($job_id){
          $result['status']   = 200;
          $result['message']  = "Post job Successfully";
           return $result;   
        }else{

        $result['status']    = 204;     
        $result['message']   ='Failed to post job';
          return $result;
      }
        
    }
		echo json_encode(array('status'=>200,'message'=>'Payment successfully'));
					
        } catch(\Stripe\Error\Card $e) {
		  	// Since it's a decline, \Stripe\Error\Card will be caught
		  	$body = $e->getJsonBody();
		  	$err  = $body['error'];

		  	$results['status'] = $e->getHttpStatus();
		  	$results['type']   = $err['type'];
		  	$results['code']   = $err['code'];
		 	// param is '' in this case
		  	$results['param']  = $err['param'];
		  	$results['message'] = $err['message'];

		  	echo json_encode(array('status' => 204, 'message' =>$results['message'],'data' =>$results));	die;
		
		} catch (\Stripe\Error\RateLimit $e) {

		  	// Too many requests made to the API too quickly

		  	$body = $e->getJsonBody();
		  	$err  = $body['error'];

		  	$results['message'] = 'Too many requests made to the API too quickly';
			echo json_encode(array('status' => 204, 'message' => $results['message'], 'data' => $err));	die;
		
		} catch (\Stripe\Error\InvalidRequest $e) {

		  // Invalid parameters were supplied to Stripe's API
			$body = $e->getJsonBody();
		  	$err  = $body['error'];

			$results['message']	 = "Invalid parameters were supplied to Stripe's API";
			echo json_encode(array('status' => 204, 'message' => $results['message'], 'data' => $err)); die;

		} catch (\Stripe\Error\Authentication $e) {

		  // Authentication with Stripe's API failed
		  // (maybe you changed API keys recently)
			$body = $e->getJsonBody();
		  	$err  = $body['error'];

			echo json_encode(array('status' => 204, 'message' => "Authentication with Stripe's API failed (maybe you changed API keys recently)", 'data' => $err)); die;	
		
		} catch (\Stripe\Error\ApiConnection $e) {
		  // Network communication with Stripe failed

			$body = $e->getJsonBody();
		  	$err  = $body['error'];

			$results['message']	 =  "Network communication with Stripe failed";
		    echo json_encode(array('status' => 204, 'message' => $results['message'], 'data' => $err)); die;

		} catch (\Stripe\Error\Base $e) {
		  // Display a very generic error to the user, and maybe send
		  // yourself an email

			$body = $e->getJsonBody();
		  	$err  = $body['error'];

			$results['message']	 =  "Error";
			echo json_encode(array('status' => 204, 'message' => $results['message'], 'data' => $err));	die;
		
		} catch (Exception $e) {
		  // Something else happened, completely unrelated to Stripe
			$results['message']	 = "Something else happened, completely unrelated to Stripe";
			
			$body = $e->getJsonBody();
		  	$err  = $body['error'];

			echo json_encode(array('status' => 204, 'message' => $results['message'], 'data' => $err));die;
		}

        /*}else{
	         echo json_encode(array('status' => 'False', 'message' => 'please fill all fields'));
            }*/

       
}
/*****************************/
/* Add Bank Account */
/*****************************/
 public function addBankAccount(Request $req){
 
    $rememberToken = $req->header('token');
          $checktoken= $this->GetCheckToken($req->input('user_id'),$rememberToken);
               if(!$checktoken){
                   return response(array('message'=>'Wrong token entered!.Please try again'), UNAUTHORIZED)->header('Content-Type', 'application/json');
               }


  $user_id=$req->input('user_id');
  
  $url='https://connect.stripe.com/express/oauth/authorize?redirect_uri=http://142.4.10.93/~vooap/touch_massage/api/webservices/add_Bank_Details&client_id=ca_DoC7tpuPyCKPwb0ifZ4XmzvQE2Vkb2ZO&state='.$user_id;

 $getAccountDetail = DB::table('add_banks')->where('user_id',$user_id)->select('account_id','user_id')->first();

   if(empty($getAccountDetail)){
       $jsonArray['url'] = $url; 
       return response(array('message'=>'Bank Details has been added successfully','data'=>$jsonArray),SUCCESS)->header('Content-Type','application/json');  


   }else{
      Stripe::setApiKey(env('STRIPE_SECRET'));
      $accDetail = $getAccountDetail->account_id; 

      $account = \Stripe\Account::retrieve($accDetail);
      $link =  $account->login_links->create();

      $urlLink = $link->url;

      $jsonArray['code'] = $getAccountDetail->account_id;
      $jsonArray['user_id'] = $getAccountDetail->user_id;
      $jsonArray['url'] =$urlLink;
      }
      return response(array('message'=>'Bank Details has been added successfully','data'=>$jsonArray),SUCCESS)->header('Content-Type','application/json');



 }
/*******************************/
/* bank_details */
/*******************************/
public function add_Bank_Details(){

    $user_id =  $_GET['state'];
    $code = $_GET['code'];

    define('TOKEN_URI', 'https://connect.stripe.com/oauth/token');
    define('AUTHORIZE_URI', 'https://connect.stripe.com/oauth/authorize');

    Stripe::setApiKey(env('STRIPE_SECRET'));

    $clientId = 'ca_DoC7tpuPyCKPwb0ifZ4XmzvQE2Vkb2ZO';
    $token_request_body = array(
        'client_secret' =>'sk_test_g685D0R3qQuJ6aWhGNt2ZBOb',
        'grant_type' => 'authorization_code',
        'client_id' => $clientId,
        'code' => $code );

      $req = curl_init(TOKEN_URI);
      curl_setopt($req, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($req, CURLOPT_POST, true );
      curl_setopt($req, CURLOPT_POSTFIELDS, http_build_query($token_request_body));

      // TODO: Additional error handling
      $respCode = curl_getinfo($req, CURLINFO_HTTP_CODE);

      $resp = json_decode(curl_exec($req), true);
      curl_close($req);
   
	  $acc = $resp['stripe_user_id'];
	  $add = DB::table('add_banks')->insert(array('user_id'=>$user_id,'account_id'=>$acc));
	    
	  if($add){
        return response(array('message'=>'Bank Details has been added successfully'),SUCCESS)->header('Content-Type','application/json');
	  }
    }

/******/ 
    }
/******/

