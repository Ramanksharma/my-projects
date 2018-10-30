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

class Usercontroller extends Controller
{

  private $required_field_error = "Please fill all fields!";
  private $unauthorized = array('message' => 'Email or password is incorrect');
  private $logoutSuccess = array('message' => 'You are logged out successfully');
  private $tokenExpire = array('message' => 'Authentication failed');
  private $badRequest = array('message' => 'Request failed');
  private $updateapp = array('message' => 'An updated version  of Touchmassage is available please update unless your application will not work.');
  private $emailrequest = array('message' => 'Email has already been taken.');
  private $emailnotregister = array('message' => 'The email is not registered with us.');

   /***************************************************/
  
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

    /********************************/
    /* RANDOM STRING GENERATE 
    /********************************/

    function generateRandomString($length) {
      $pool = array_merge(range(0,9),range('A', 'Z'));
      
      for($i=0; $i < $length; $i++){
           @$key .= $pool[mt_rand(0, count($pool) - 1)];
      }

         $ran= md5(uniqid(rand(), true));  
         $srtrs= round(microtime(true));
      return $key.substr($ran,3,3).substr($srtrs,3,3);
       }


    /***************************************/
    /* FUNCTION FOR CHECK TOKEN EXPIRED OR NOT
    /***************************************/

    function GetCheckToken($userid,$login_token)
    {


       $user = DB::table('users')->where('id',$userid)->where('token',$login_token)->select('*')->first();
       //print_r($user);die;
        if (empty($user)) {
          
           return false;
                
         }else{
             return $user;
         }
    }  


     /***********************************************/
    /* FUNCTION FOR CHECK User Login Another Device
    /***********************************************/
      function UserLoginanotherDevice($user,$token)
    {
       $user = DB::table('users')->where('id','=',$user)->where('token','=', $token)->select('id')->first();
        if (!$user) {
            echo json_encode(array('status' => 401, 'message' => 'It seems like you have logged in from another device. Please sign in again.'));
                die;
         }else{
             return true;
         }
    }
     /********************************/
    /* Signup 
    /********************************/

        public function signup(Request $req){

            if($req->input('registration_type') == 'O'){ // check Registration type O for normal user

            $samePassword = Validator::make($req->all(), [
                    'confirm_password'              => 'same:password'
            ]);

            if ($samePassword->fails())
            {
                return response(array('message'=>'Password and confirm Password is mismatch'), NOT_ACCEPTABLE)->header('Content-Type', 'application/json');
            }

            $validator = Validator::make($req->all(), [  
                'email'                         => 'required',
                'password'                      => 'required',
                'confirm_password'              => 'required|same:password',
                'registration_type'             => 'required'
            ]);

           }else{

            $validator = Validator::make($req->all(), [  
                'registration_type'             => 'required',       
                'social_id'                     => 'required'
                            
                ]);
           }

            if ($validator->fails()) {          
                 return response(['message' => $validator->errors()->first()], BAD_REQUEST)->header('Content-Type', 'application/json');

            }else{
                 
                $User = new User;
                $get_userdata  = DB::table('users')->where('email',$req->input('email'))->first();
                $token_key = $this->generateRandomString(10);

        
        	
             if($req->input('registration_type') == 'O'){
             if(empty($get_userdata)){
                    $User->email            = $req->input('email');
                    $User->device_type      = $req->header('devicetype');
                    $User->device_id        = $req->header('deviceid');
                    $User->token            = $token_key;
                    $User->registration_type  = $req->input('registration_type');
                    $User->user_type        = $req->input('user_type');
                    $User->app_version      = $req->header('appversion');
                    $User->password         = Hash::make($req->input('password'));

                    $User->save();                
                    $id = $User->id;
                    $datas = DB::table('users')->where('id',$id)->select('id','email','user_type','token','status','device_id','device_type')->first();
                    $profileCheck = DB::table('users')->where('id',$id)->select('user_name','notification_status')->first();
                  if($profileCheck->user_name != '')
                       {
                          $datas->profile_status = true;
                       }else{
                          $datas->profile_status = false;
                       }

                       if($profileCheck->notification_status == 'ON')
                       {
                          $datas->notification_status = true;
                       }else{
                          $datas->notification_status = false;
                       }
                  
                  return response(array('message'=>'Registration Successfully','data'=>$datas),SUCCESS)->header('Content-Type','application/json'); 
              
              }else{
                   return response($this->emailrequest, CONFLICT)->header('Content-Type', 'application/json');
                }  

             }else{
           if(@$req->input('email') && $req->input('email') !== '') {
              $get_usersocial  = DB::table('users')->where('email',$req->input('email'))->where('registration_type','!=',$req->input('registration_type'))->select('email')->first();
              if($get_usersocial){
                return response($this->emailrequest, CONFLICT)->header('Content-Type', 'application/json');
              }
            }


                
                $get_user  = DB::table('users')
                            ->where('social_id',$req->input('social_id'))
                            ->select('id','email','user_type','token','status','registration_type','app_version','device_id','social_id','device_type')
                            ->first();
                
                $token_key = $this->generateRandomString(10);
           
            if(empty($get_user)){
                    $User = new User;           
                    $User->social_id        = $req->input('social_id');
                    $User->email            = $req->input('email');
                    $User->device_type      = $req->header('devicetype');
                    $User->device_id        = $req->header('deviceid');
                    $User->token            = $token_key;
                    $User->registration_type  = $req->input('registration_type');
                    $User->user_type        = $req->input('user_type');
                    $User->app_version      = $req->header('appversion');
                   

                    $User->save();                
                    $id = $User->id;
                    //$datas = User::find($id);
                     $datas = DB::table('users')->where('id',$id)->select('id','email','user_type','token','status','device_id','device_type')->first();
                    
                     $profileCheck = DB::table('users')->where('id',$id)->select('user_name','notification_status')->first();
                  
                    if($profileCheck->user_name != ''){
                          $datas->profile_status = true;
                    }else{
                          $datas->profile_status = false;
                    }

                    if($profileCheck->notification_status == 'ON'){
                        $datas->notification_status = true;
                    }else{
                        $datas->notification_status = false;
                    }

                    return response(array('message'=>'Registration Successfully','data'=>$datas), SUCCESS)->header('Content-Type', 'application/json');
                
            }else{
                   if($get_user->status=='A'){ 
                   $id=$get_user->id;
                   	$profileCheck = DB::table('users')->where('id',$id)->select('user_name','notification_status')->first();
                  
                    if($profileCheck->user_name != ''){
                          $get_user->profile_status = true;
                    }else{
                          $get_user->profile_status = false;
                    }

                    if($profileCheck->notification_status == 'ON'){
                        $get_user->notification_status = true;
                    }else{
                        $get_user->notification_status = false;
                    }


              return response(array('message'=>'Registration Successfully','data'=>$get_user), SUCCESS)->header('Content-Type', 'application/json');    
             }else{

             return response(array('message'=>'Your account has been suspended by admin, Please contact admin@gmail.com to approve your account'), UNAUTHORIZED)->header('Content-Type', 'application/json'); 

             }

            }
         
         

         }

         /*}else{
                   return response($this->emailrequest, CONFLICT)->header('Content-Type', 'application/json');
            }*/
        }
    }
    /********************************/
    /* Login 
    /********************************/

    public function login(Request $req)
    {
         $email      = $req->input('email');
         $password   = $req->input('password');
         $deviceId   = $req->header('deviceid');
         $deviceType = $req->header('devicetype');
         $user_type  = $req->input('user_type');

         $validator  = Validator::make($req->all(), [  
                        'email'         => 'required',
                        'password'      => 'required'
                       
                        ]);

            if ($validator->fails()) {          
                 return response(['message' => $validator->errors()->first()], BAD_REQUEST)->header('Content-Type', 'application/json');

            }else{
     
            if (Auth::attempt(['email' => "$email", 'password' => "$password"])){
                $userId = Auth::id();
                $data   = User::find($userId);
                $resultstatus=$this->webserviceModel->getusers($data->id);
                   
            if ($resultstatus->status=='A'){ //chech user is active or suspend
            if ($resultstatus->user_type == $user_type){ 

                $token_key   = $this->generateRandomString(10);
                $this->webserviceModel->UpdateLoginToken($data->id,$token_key,$deviceType,$deviceId);
                //$datas = User::find($data->id);
                 $datas = DB::table('users')->where('id',$data->id)->select('id','email','user_type','token','device_id','device_type')->first();

                $profileCheck = DB::table('users')->where('id',$data->id)->select('user_name','notification_status')->first();
                 
                if($profileCheck->user_name != ''){
                    $datas->profile_status = true;
                }else{
                    $datas->profile_status = false;
                }

                if($profileCheck->notification_status == 'ON'){
                    $datas->notification_status = true;
                }else{
                    $datas->notification_status = false;
                }

             return response(array('message'=>'Login Successfully','data'=>$datas), SUCCESS)->header('Content-Type', 'application/json');

             }else{
             	return response(array('message'=>'invalid user'), NOT_ACCEPTABLE)->header('Content-Type', 'application/json');
             }

            }else{

             return response(array('message'=>'Your account has been suspended by admin, Please contact admin@gmail.com to approve your account'), UNAUTHORIZED)->header('Content-Type', 'application/json'); 
             }

           }else{
             return response($this->unauthorized, NOT_ACCEPTABLE)->header('Content-Type', 'application/json');
           }
           
        }

    }

   /********************************/
  /* Logout
  /********************************/

   public function logout(Request $req){

    $validator = Validator::make($req->all(),[
      'user_id'       => 'required'
    ]);   

      if ($validator->fails()) {      
        return response(['message' => $validator->errors()->first()], BAD_REQUEST)->header('Content-Type', 'application/json');
      }else{
                             
          $User = new User();
          $User = User::find($req->input('user_id')); 
          $User->token = '';
          $User->device_id   = '';
          $User->device_type = '';

          if($User->save()){          
          return response($this->logoutSuccess, SUCCESS)->header('Content-Type', 'application/json');
          } else {
            return response($this->unauthorized, NOT_ACCEPTABLE)->header('Content-Type', 'application/json');
          } 
            }
          }
   
   /********************************/
  /* Forgot Password API
  /********************************/

  public function forgotPassword()
  {
      $user   = new User();
      $email    = Input::get('email');
     

      if($email != '')
      {
       $password_token = $this->generateRandomString(10);
       $fake_token_one = $this->generateRandomString(2);
       $fake_token_two = $this->generateRandomString(3);

       
       $useremail = DB::table('users')
                    ->where('email', $email)
                    ->select('*')->first();
      
    if (!$useremail)
    {
      
      return response($this->emailnotregister, BAD_REQUEST)->header('Content-Type', 'application/json');

    }else{  

       if($useremail->registration_type != 'O'){
                return response(array('message'=>'Sorry, This account has  been linked through social media account'), NOT_ACCEPTABLE)->header('Content-Type', 'application/json');
            }


       $update_user_data = DB::table('users')->where('email',$email)
                ->update(array('password_token' => $password_token));

    $userid = $useremail->id; 
    $base_url = url('/');

      $content ="<p>We have received your request for change password.</p>
          <p>Please 
          <a href='".$base_url."/api/resetPasswordForm/$fake_token_one-$fake_token_two-$userid-$password_token'>Click here </a> to change your password.</p><br/><br/>Thanks<br/>Touch Massage";

      $email =  Input::get('email');

      Mail::send(array(), array(), function ($message) use ($content,$email) 
      {
          $from  = 'info@touchmassage.com';
          $message->to($email ,'Forgot Password')
          ->subject('Request for change password')
          ->setBody($content, 'text/html');

      });

        return response(array('message'=>'Please check your mail address to reset your password'), SUCCESS)->header('Content-Type', 'application/json');  
        }
      }else{

      return response(array('message'=>'Please Enter Email Address'), BAD_REQUEST)->header('Content-Type', 'application/json');

      }
  }


    /**************************************
     Reset password when user click 
     on click from mail 
  ***********************************/  

  public function resetPasswordForm($token)
  {
      $tokenn = explode("-", $token);
      $user_id=$tokenn[2];
      $password_token=$tokenn[3];
      $error= '';
  
     $result_data = $this->webserviceModel->GetPasswordToken($password_token,$user_id);

    if(!$result_data){
      $error = 'Password token has been expired!!';
      
    }
     return View::make('emails.resetpasswordform', ['error' => $error,'token' => $password_token,'user_id'=>$user_id]);
  }


  /************************************
     Set New password after fill the
     reset password form by user
  **************************************/

  public function setNewPassword()
  {
    $password_token   = htmlspecialchars(trim(Input::get('token')));
    $user_id          = htmlspecialchars(trim(Input::get('user_id')));
    $password         = htmlspecialchars(trim(Input::get('password')));
    $repeat_password  = htmlspecialchars(trim(Input::get('repeat_password')));

      $result_data = $this->webserviceModel->GetPasswordToken($password_token,$user_id);      
    if($result_data && $password_token !='')
    {
      if($password == $repeat_password && $password != '' && $repeat_password != '')
      { 
        $UpdateDetailObj = DB::table('users')
         ->where('id',$user_id)
         ->limit(1)
         ->update(array('password' =>Hash::make($password),'password_token' =>''));
      
      $message = "Password Reset Successfully!";      
      return View::make('emails.resetpasswordform', ['message' => $message]);

      }else{
        $error = "Password Not Match!";
        $password_token = $password_token;
        return View::make('emails.resetpasswordform', ['error' => $error,'token' => $password_token,'user_id'=>$user_id]); 
      } 
     }else{     
        $expire_token = "Password token expired! Please resend mail to get new token.";
        return View::make('emails.resetpasswordform', ['expire_token' => $expire_token]);    
      }
  }

 /**************************************/
 /* Create profile api */
 /**************************************/
   public function createProfile(Request $req){
   
      $validator=validator::make($req->all(),[
            'user_id'          => 'required',
            'first_name'       => 'required',
            'last_name'        => 'required',
            'address'          => 'required',
            'mobnumber'        => 'required', 
            'gender'           => 'required', 
            'user_type'        => 'required'
            ]); 
            
      if ($req->input('user_type')=='SP'){

        $validator=validator::make($req->all(),[
            'about_me'         => 'required', 
            /*'id_card'          => 'required',*/
            'experience'       => 'required',
            'user_id'          => 'required',
            'first_name'       => 'required',
            'last_name'        => 'required',
            'address'          => 'required',
            'mobnumber'        => 'required', 
            'gender'           => 'required', 
            'user_type'        => 'required', 
            'services'         => 'required'

      ]);
    }

    if ($validator->fails()) {          
               
    return response(['message' => $validator->errors()->first()], BAD_REQUEST)->header('Content-Type', 'application/json');
            }else{

          $rememberToken = $req->header('token');
          $checktoken= $this->GetCheckToken($req->input('user_id'),$rememberToken);
            
            if(!$checktoken){
                   return response(array('message'=>'Wrong token entered!.Please try again'), UNAUTHORIZED)->header('Content-Type', 'application/json');
               }

            if($checktoken->status == 'S'){
            	 return response(array('message'=>'Your account has been suspended by admin, Please contact admin@gmail.com to approve your account'), UNAUTHORIZED)->header('Content-Type', 'application/json'); 
            }


            if($req->input('user_type')=='SP'){

          $service_provider = array(
                  'user_id' =>$req->input('user_id'),
                  'first_name' =>$req->input('first_name'),
                  'last_name' =>$req->input('last_name'),
                  'address' =>$req->input('address'),
                  'mobnumber' =>$req->input('mobnumber'),
                  'gender' =>$req->input('gender'),
                  'about' =>$req->input('about_me'),
                  'experience' =>$req->input('experience'),
                  'services' =>$req->input('services'));
            
                  If(Input::hasFile('profile_picture')){

                          $extension = Input::file('profile_picture')->getClientOriginalExtension(); 
                          $destinationPath = base_path('/') . '/public/uploads/profile';
                          $profileImage = round(microtime(true)).'.'.$extension;
                          Input::file('profile_picture')->move($destinationPath, $profileImage); 
                          $service_provider['profile_picture']=$profileImage;
                      }
                      
                       If(Input::hasFile('id_card')){

                          $extension = Input::file('id_card')->getClientOriginalExtension(); 
                          $destinationPath = base_path('/') . '/public/uploads/idCard';
                          $id_card = round(microtime(true)).'.'.$extension;
                          Input::file('id_card')->move($destinationPath, $id_card); 
                          $service_provider['id_card']=$id_card;
                }
                    $id=$req->input('user_id');
                    $data_result=DB::table('serviceprovider_profiles')->where('user_id',$id)->select('*')->first();
                     
                if($data_result){
                     
                    $result_data= DB::table('serviceprovider_profiles')->where('user_id',$id)->update($service_provider);
                       
                }else{
                    
                    $result_data=$this->webserviceModel->addprofile('serviceprovider_profiles',$service_provider);
                    
                    }

                    
                    $data_result=DB::table('serviceprovider_profiles')->where('user_id',$id)->select('profile_picture')->first();
                    DB::table('users')->where(['id'=>$id])->update(['profile_picture'=>$data_result->profile_picture]);
        }else{

              $user_profile = array(
                  'user_id' =>$req->input('user_id'),
                  'first_name' =>$req->input('first_name'),
                  'last_name' =>$req->input('last_name'),
                  'address' =>$req->input('address'),
                  'mobnumber' =>$req->input('mobnumber'),
                  'gender' =>$req->input('gender'));


                  If(Input::hasFile('profile_picture')){

                          $extension = Input::file('profile_picture')->getClientOriginalExtension(); 
                          $destinationPath = base_path('/') . '/public/uploads/profile';
                          $profileImage = round(microtime(true)).'.'.$extension;
                          Input::file('profile_picture')->move($destinationPath, $profileImage); 
                          $user_profile['profile_picture']=$profileImage;
                      }

                  $id=$req->input('user_id');
                  $data_result=DB::table('user_profiles')->where('user_id',$id)->select('*')->first();
              
              if($data_result){
                  $result_data= DB::table('user_profiles')->where('user_id',$id)->update($user_profile);
               }else{
              
                  $result_data=$this->webserviceModel->addprofile('user_profiles',$user_profile);
                }     
                  $data_result=DB::table('user_profiles')->where('user_id',$id)->select('profile_picture')->first();

                  DB::table('users')->where(['id'=>$id])->update(['profile_picture'=>$data_result->profile_picture]);
        }
                  $id=$req->input('user_id');
                  $user_profile_add = array('user_name' =>$req->input('first_name'));
                  DB::table('users')->where(['id'=>$id])->update($user_profile_add);
                
          if($result_data){     
                 return response(array('message'=>'Profile create Successfully'), SUCCESS)->header('Content-Type', 'application/json');  
          }else{
                  return response(array('message'=>'Profile has been update succssfully'), SUCCESS)->header('Content-Type', 'application/json');
            }
        }
     }
  


/***********************************/
 /*  Add Bank */
/***********************************/
public function addBank(Request $req){

 $validator=validator::make($req->all(),[
            'user_id'        => 'required', 
            'user_name'      => 'required', 
            'bank_name'      => 'required', 
            'account_number' => 'required', 
            'routing_number' => 'required' 
        ]);
    

    if ($validator->fails()) {          
        return response(['message' => $validator->errors()->first()], BAD_REQUEST)->header('Content-Type', 'application/json');

            }else{

             $rememberToken = $req->header('token');
             $checktoken= $this->GetCheckToken($req->input('user_id'),$rememberToken);
               if(!$checktoken){
                   return response(array('message'=>'Wrong token entered!.Please try again'), UNAUTHORIZED)->header('Content-Type', 'application/json');
               }

             $addbank = array(
                  'user_id' =>$req->input('user_id'),
                  'user_name' =>$req->input('user_name'),
                  'bank_name' =>$req->input('bank_name'),
                  'account_number' =>$req->input('account_number'),
                  'routing_number' =>$req->input('routing_number')
                  );
              $result_data=$this->webserviceModel->insertdata('add_banks',$addbank);


        if($result_data){
          return response(array('message'=>'Bank add Successfully'), SUCCESS)->header('Content-Type', 'application/json'); 
        }else{
          return response(array('message'=>'Failed to create profile'), BAD_REQUEST)->header('Content-Type', 'application/json');
      }
    }

}
/******************************/
/* User Home page */
/******************************/

  public function userHome(Request $req)
  {
    $result_data=$this->webserviceModel->userHome();

     if($result_data){
         
           return response(array('message'=>'Data found','data'=>$result_data), SUCCESS)->header('Content-Type', 'application/json');    
        }else{

            return response(array('message'=>'Data not found'), BAD_REQUEST)->header('Content-Type', 'application/json');    
     }
}


/********************************/
/* Add New Services */
/********************************/
/*public function addNewService(Request $req){

   $validator=validator::make($req->all(),[
            'user_id'            => 'required',
            'service_id'         => 'required',
            'therapist_gender'   => 'required',
            'massage_length'     => 'required',
            'start_date'         => 'required',
            'start_time'         => 'required',
            'street'             => 'required',
            'city'               => 'required',
            'zip'                => 'required',
            'state'              => 'required',
            'price'              => 'required',
            'parking_instruction'=> 'required']);
    

    if ($validator->fails()) {          
                $result['status']    = 204;         
                $result['message']   = $this->required_field_error;
                return $result;

            }else{

                 $rememberToken = $req->header('token');
              $this->GetCheckToken($req->input('user_id'),$rememberToken);

          $data = array(
              'user_id' =>$req->input('user_id'),
              'services_id' =>$req->input('service_id'),
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
              'job_forword' =>'NA',
              'job_status' =>'N');

          $result_data=$this->webserviceModel->insertdata('post_services',$data);

        if($result_data){
          $result['status']   = 200;
          $result['message']  = "Post job Successfully";
           return $result;   
        }else{

        $result['status']    = 204;     
        $result['message']   ='Failed to post job';
          return $result;
      }

        }

}*/

/*********************************************/
/*Get More services */
/**********************************************/

  Public function getOtherServices($data){
   	   $services_id=explode(',', $data);
       $result_data= array();
   	   foreach ($services_id as $key => $value) {
             $result=DB::table('services_list')
                         ->where('id',$value)
                         ->select('title')
                         ->first();                   	   	
   	        if($result){
   	        	$result_data[]=$result->title;
   	    }
    }

           return $result_data;
   }



/************************************/
/* Send Message */
/**************************************/
   public function send_message(Request $request){

      	$validator=validator::make($request->all(),[
                'sender_id'  => 'required',
                'reciver_id' => 'required',
                'message'    => 'required' ]);
    
        if ($validator->fails()) {          
        
            return response(['message' => $validator->errors()->first()], BAD_REQUEST)->header('Content-Type', 'application/json');
        
        }else{

            $rememberToken = $request->header('token');
            $checktoken= $this->GetCheckToken($request->input('sender_id'),$rememberToken);
         
            if(!$checktoken){
            return response(array('message'=>'Wrong token entered!.Please try again'), UNAUTHORIZED)->header('Content-Type', 'application/json');
            }
            if($checktoken->status == 'S'){
            	 return response(array('message'=>'Your account has been suspended by admin, Please contact admin@gmail.com to approve your account'), UNAUTHORIZED)->header('Content-Type', 'application/json'); 
            }
        

		        $sender_id   = $request->input('sender_id');
		        $receiver_id = $request->input('reciver_id');
		        $job_id      = $request->input('job_id');
		        $message     = $request->input('message');

                $result=$this->webserviceModel->checkConnection($sender_id,$receiver_id,$job_id);

        if(!empty($result)){
         	
         	    $connection_id=$result->id;
        
        }else{

         	    $this->webserviceModel->add_connection($sender_id,$receiver_id,$job_id);
        }
                $result=$this->webserviceModel->checkConnection($sender_id,$receiver_id,$job_id);
        
        if(!empty($result)){
        
         	    $connection_id=$result->id;
         	    $this->webserviceModel->insert_message($sender_id,$message,$connection_id);
        }
         
        return response(array('message'=>'Data save succssfully'), SUCCESS)->header('Content-Type', 'application/json');  
    }
 }

 


 /******************************/
 /*get My Connection*/
 /******************************/
  public function getMyConnection($user_id){
      
           $data    = DB::table('connection')
                   ->where('del','!=',$user_id)
                   ->where('del','!=','on')
                   ->where('sender_id',$user_id)
                   ->orwhere('reciver_id',$user_id)
                   ->select('*')
                   ->get();

        $mydata=array();
        foreach ($data as  $value) {
        	    $sender_id = $value->sender_id;
        	    if($sender_id == $user_id){
                   $userInfo = $this->webserviceModel->getUserInfo($value->reciver_id);

                   $myconnection['connection_id']=$value->id;
                   
                    if(!empty($userInfo)){
                        $myconnection['name'] = $userInfo->user_name;
                    if(!empty($userInfo->profile_picture)){
                        $myconnection['profile_picture']=URL('/').'/public/uploads/profile/'.$userInfo->profile_picture;
                    }else{
                        	$myconnection['profile_picture']="";
                    }

                    $mydata[]=$myconnection;
                   }
        	    }else{
                    $myconnection['connection_id']=$value->id;
                    $userInfo=$this->webserviceModel->getUserInfo($value->sender_id);

                    if(!empty($userInfo)){
                        $myconnection['name']=$userInfo->user_name;
                    if(!empty($userInfo->profile_picture)){
                        $myconnection['profile_picture']=URL('/').'/public/uploads/profile/'.$userInfo->profile_picture;
                    }else{
                        	$myconnection['profile_picture']="";
                    }
                        $mydata[]=$myconnection;
                   }
        	    }
             }
            return response(array('message'=>'Data found','data'=>$mydata), SUCCESS)->header('Content-Type', 'application/json');    
    }



    /********************************************************/
    /* delete the single message and multipal messages*/
    /********************************************************/
/*    public function deleteconnection(Request $request)
    {
    	$user_id = $request->input('user_id');
    	$conversation_id = $request->input('connection_id');
        $message_id=explode(',', $conversation_id);

      	foreach ($message_id as $key => $value) {

      		$result = $this->webserviceModel->getdata('conversation',$value,$user_id);

      		if(!empty($result->del)){
             	DB::table('conversation')->where(['id'=>$value])->update(['del'=>'on']);          	
          	}else{
          	 	DB::table('conversation')->where(['id'=>$value])->update(['del'=>$user_id]);
          	} 
        }              
                         
    }*/
      public function deleteconnection(Request $request)
    {
      $user_id = $request->input('user_id');
      $connection_id = $request->input('connection_id');
 
      $result = $this->webserviceModel->getchatdata('conversation',$connection_id,$user_id);
      if(!empty($result->del)){
          $delconnection=DB::table('conversation')->where(['connection_id'=>$connection_id])->update(['del'=>'on']); 
      }else{
         $delconnection=DB::table('conversation')->where(['connection_id'=>$connection_id])->update(['del'=>$user_id]);
      }       
         $result = $this->webserviceModel->getdata('connection',$connection_id,$user_id);
      if(!empty($result->del)){
         $delconnection=DB::table('connection')->where(['id'=>$connection_id])->update(['del'=>'on']); 
      }else{
         $delconnection=DB::table('connection')->where(['id'=>$connection_id])->update(['del'=>$user_id]);
      }          
            
      if($delconnection){
        
        return response(array('message'=>'connection delete succssfully'), SUCCESS)->header('Content-Type', 'application/json'); 
      }else{
        return response(array('message'=>'failed to delete Connection'), BAD_REQUEST)->header('Content-Type', 'application/json');
      }           
                         
    }
/******************************/
/*get my chat list*/
/******************************/
  public function getMychat(Request $req,$user_id,$connection_id)
   {
   	     
      $rememberToken = $req->header('token');
      $checktoken= $this->GetCheckToken($user_id,$rememberToken);
     
        if(!$checktoken){
          return response(array('message'=>'Wrong token entered!.Please try again'), UNAUTHORIZED)->header('Content-Type', 'application/json');
        } 
        if($checktoken->status == 'S'){
            	 return response(array('message'=>'Your account has been suspended by admin, Please contact admin@gmail.com to approve your account'), UNAUTHORIZED)->header('Content-Type', 'application/json'); 
            }


        $result_data=DB::table('conversation as c')
                ->join('users as u','u.id','=','c.user_id')
                ->where('connection_id','=',$connection_id)
                ->where('del','!=',$user_id)
                /*->where('del','!=','on')*/
                ->select('c.message','u.user_name','u.id as user_id')
                ->get();

              if($result_data){
 
                return response(array('message'=>'Data found','data'=>$result_data), SUCCESS)->header('Content-Type', 'application/json');  
            
            }else{
               return response(array('message'=>'Data not found'), BAD_REQUEST)->header('Content-Type', 'application/json');
            }
       
    }

/*********************************/
/* Give Feedback */
/*********************************/
  
  public function giveFeedback(Request $req){
    
  $validator=validator::make($req->all(),[
            'user_id'    => 'required',
            'ratable_id' => 'required',
            'rating'     => 'required',
            'user_type'  => 'required',
            'job_id'     => 'required',
            'comments'   => 'required'
             /*'token'=>'required'*/
             ]);
    
        if ($validator->fails()) {          
          return response(['message' => $validator->errors()->first()], BAD_REQUEST)->header('Content-Type', 'application/json');
        }else{

            $rememberToken = $req->header('token');
            $checktoken= $this->GetCheckToken($req->input('user_id'),$rememberToken);
            
            if(!$checktoken){
                   return response(array('message'=>'Wrong token entered!.Please try again'), UNAUTHORIZED)->header('Content-Type', 'application/json');
            }

            if($checktoken->status == 'S'){
            	 return response(array('message'=>'Your account has been suspended by admin, Please contact admin@gmail.com to approve your account'), UNAUTHORIZED)->header('Content-Type', 'application/json'); 
            }

       $data_result = array(
                     'rater_id' => $req->input('user_id'), 
                     'job_id' => $req->input('job_id'), 
                     'ratable_id' => $req->input('ratable_id'), 
                     'rating' => $req->input('rating'), 
                     'comments' => $req->input('comments')
                     );
                
           $result_data=DB::table('feedbacks')->insert($data_result);

          $type=$req->input('user_type');
          if($type=='SP'){
             $sp_data=DB::table('serviceprovider_profiles')->where('user_id',$req->input('user_id'))->select('first_name','last_name')->first();
           }else{
             $sp_data=DB::table('user_profiles')->where('user_id',$req->input('user_id'))->select('first_name','last_name')->first();
           }


          $message= $sp_data->first_name.' '.$sp_data->last_name.' give you a feedback';
          $label = "feedBack";
          $sender_id=$req->input('user_id');

         $this->Sent_notification($req->input('ratable_id'),$sender_id,$label,$message,$type);

          if($result_data){
            
            return response(array('message'=>'Your feedback save succssfully'), SUCCESS)->header('Content-Type', 'application/json');
            }else{
            return response(array('message'=>'Failed to save feedback'), BAD_REQUEST)->header('Content-Type', 'application/json');
            }
        }
  }
/******************************/
/*Get my profile*/
/******************************/

 public function  getMyProfile(Request $req,$user_id,$user_type){
    
          $rememberToken = $req->header('token');
          $checktoken= $this->GetCheckToken($user_id,$rememberToken);
               
               if(!$checktoken){
                   return response(array('message'=>'Wrong token entered!.Please try again'), UNAUTHORIZED)->header('Content-Type', 'application/json');
               } 
                if($checktoken->status == 'S'){
            	 return response(array('message'=>'Your account has been suspended by admin, Please contact admin@gmail.com to approve your account'), UNAUTHORIZED)->header('Content-Type', 'application/json'); 
            }

              $result_data=$this->webserviceModel->getMyProfiles($user_id,$user_type);
             
               if($user_type=='SP'){
               if(empty($result_data[0]->id_card)){
                        $result_data[0]->id_card="";
               } 
                if(empty($result_data[0]->profile_picture)){
                        $result_data[0]->profile_picture="";
               } 

             }else{

              if(empty($result_data->profile_picture)){
                        $result_data->profile_picture="";
               }
             }


              if($result_data){   
             return response(array('message'=>'Data found','data'=>$result_data), SUCCESS)->header('Content-Type', 'application/json');   
            }else{
              return response(array('message'=>'Data not found'), BAD_REQUEST)->header('Content-Type', 'application/json');
            }
       
 }
  /**********************************/
  /*Get History */
  /**********************************/
public function getHistory($user_id,$user_type,$status){
 
            $rememberToken = $req->header('token');
            $checktoken= $this->GetCheckToken($user_id,$rememberToken);
               if(!$checktoken){
                   return response(array('message'=>'Wrong token entered!.Please try again'), UNAUTHORIZED)->header('Content-Type', 'application/json');
               }

                if($checktoken->status == 'S'){
            	 return response(array('message'=>'Your account has been suspended by admin, Please contact admin@gmail.com to approve your account'), UNAUTHORIZED)->header('Content-Type', 'application/json'); 
            }

            /************Show jobs for user side*******************/
      if($user_type=='U'){  

            if($status=='U'){   // if status upcoming then show new jobs and accepted jobs
 
           $result_data=DB::table('post_services')
                       ->where('user_id' , $user_id)
                       ->where('job_status' ,'N')
                       ->orwhere('job_status','AC')
                       ->select('services_id','massage_length','start_date','start_time','job_forword','id as job_id','user_id')
                       ->get();

            }else{

              if($status=='IP'){
               $job_status='ST';
            }else{
              $job_status=$status;
            }


              $result_data=DB::table('post_services')
                       ->where('user_id' , $user_id)
                       ->where('job_status' , $job_status)
                       ->select('services_id','massage_length','start_date','start_time','job_forword','id as job_id','user_id')
                       ->get();

            }

           foreach ($result_data as $k => $value)
            {
                $job_forword=$value->job_forword;
              if($job_forword!='' ){
                $message_result=true;
              }else{
                $message_result=false;
              }
              $result_data[$k]->job_forword_status=$message_result;
              $moreservices=$this->getOtherServices($value->services_id);
              $result_data[$k]->services_name = $moreservices;

                if($status=='Com'){

              $getFeedBack=$this->webserviceModel->getFeedBack($value->job_id,$user_id);
               $result_data[$k]->FeedBack = $getFeedBack;
            }
           } 
         }else{
    /************Show jobs for service provider  side*******************/



            if($status=='U'){
               $job_status='AC';
            }else{
              $job_status=$status;
            }

            if($status=='IP'){
               $job_status='ST';
            }else{
              $job_status=$status;
            }
           $result_data=DB::table('post_services')
                       ->where('job_forword' , $user_id)
                       ->where('job_status' , $job_status)
                       ->select('services_id','massage_length','start_date','start_time','job_forword','id as job_id','job_status')
                       ->get();
          

               foreach ($result_data as $k => $value)
            {
                $job_forword=$value->job_forword;   // jobforword status is used for tracking button
              if($job_forword!='' ){
                $message_result=true;
              }else{
                $message_result=false;
              }
              $result_data[$k]->job_forword=$message_result;
              $moreservices=$this->getOtherServices($value->services_id);
              $result_data[$k]->services_name = $moreservices;
              
             if($status=='Com'){

              $getFeedBack=$this->webserviceModel->getFeedBack($value->job_id,$user_id);
               $result_data[$k]->FeedBack = $getFeedBack;
            }
             
           } 
         }

           if(!$result_data->isEmpty()){

            return response(array('message'=>'Data found','data'=>$result_data), SUCCESS)->header('Content-Type', 'application/json');      
            }else{
             return response(array('message'=>'Data not found'), BAD_REQUEST)->header('Content-Type', 'application/json');
            }
        
}
/****************************/
/* Get Service Provider Home  */
/****************************/
public function spHome(Request $req,$user_id){

        $rememberToken=$req->header('token');
        $checktoken= $this->GetCheckToken($user_id,$rememberToken);
               
        if(!$checktoken){
            return response(array('message'=>'Wrong token entered!.Please try again'), UNAUTHORIZED)->header('Content-Type', 'application/json');
        }

         if($checktoken->status == 'S'){
            	 return response(array('message'=>'Your account has been suspended by admin, Please contact admin@gmail.com to approve your account'), UNAUTHORIZED)->header('Content-Type', 'application/json'); 
            }

           $result_data=DB::table('post_services')
                       ->where('job_forword' , $user_id)
                       ->where('job_status' , 'N')
                       ->select('services_id','massage_length','start_date','start_time','job_forword','id as job_id')
                       ->get();

               foreach ($result_data as $k => $value)
            {
                $job_forword=$value->job_forword;

              if($job_forword!='' ){
                $message_result=true;
              }else{
                $message_result=false;
              }
                $result_data[$k]->job_forword=$message_result;
                $moreservices=$this->getOtherServices($value->services_id);
                $result_data[$k]->services_name = $moreservices;
           } 
         

           if(!$result_data->isEmpty()){
             return response(array('message'=>'Data found','data'=>$result_data), SUCCESS)->header('Content-Type', 'application/json');      
            }else{
             return response(array('message'=>'Data not found'), BAD_REQUEST)->header('Content-Type', 'application/json');
            }
        }

/***************************************************/
/*Update Job Status (Accept,Reject,Cancel,Startjob)*/
/***************************************************/
    public function updateJobStatus(Request $req){
      
       $validator=validator::make($req->all(),[
            'user_id'    => 'required',
            'job_id'    => 'required',
            'status' =>'required'
             ]);
    
        if ($validator->fails()) {    
                return response(['message' => $validator->errors()->first()], BAD_REQUEST)->header('Content-Type', 'application/json');
        }else{ 

               $rememberToken = $req->header('token');

             $checktoken= $this->GetCheckToken($req->input('user_id'),$rememberToken);
               if(!$checktoken){
                   return response(array('message'=>'Wrong token entered!.Please try again'), UNAUTHORIZED)->header('Content-Type', 'application/json');
               }

                if($checktoken->status == 'S'){
            	 return response(array('message'=>'Your account has been suspended by admin, Please contact admin@gmail.com to approve your account'), UNAUTHORIZED)->header('Content-Type', 'application/json'); 
            }


          $job_id=$req->input('job_id');
          $status=$req->input('status');
          $user_id=$req->input('user_id');
            
          if($status=='AC'){ // send notification to user for that service provider has been assigned to you
            $this->jobNotification($job_id,$user_id);
          }
        if($status=='IP'){
          $data=DB::table('serviceprovider_profiles')
                ->where('user_id',$user_id)
                ->update(['online'=>'OFF']);
        }
        if($status=='Com'){
          $data=DB::table('serviceprovider_profiles')
                ->where('user_id',$user_id)
                ->update(['online'=>'ON']);
        }
          if($status=='C'){
          $data=DB::table('post_services')
                ->where('id',$job_id)
                ->update(['cancel_at'=> date('Y-m-d H:i:s')]);
        }

          $data=DB::table('post_services')
                ->where('id',$job_id)
                ->update(['job_status'=>$status]);


          if($data){
            return response(array('message'=>'Data update Successfully'), SUCCESS)->header('Content-Type', 'application/json');      
            }else{
             return response(array('message'=>'Failed to update data'), BAD_REQUEST)->header('Content-Type', 'application/json');
            }
        }
    }

  /*********************************/
  /* Cancel and Reject Job */
  /*********************************/  

   public function CancelRejectJob(Request $req){
        $validator=validator::make($req->all(),[
            'job_id'    => 'required',
            'user_id'   => 'required',
            'reason'    => 'required',
            'status'    =>'required'
             /*'token'=>'required'*/
             ]);
    
        if ($validator->fails()) {    
                return response(['message' => $validator->errors()->first()], BAD_REQUEST)->header('Content-Type', 'application/json');
        }else{ 

              $rememberToken = $req->header('token');
              $checktoken= $this->GetCheckToken($req->input('user_id'),$rememberToken);
               if(!$checktoken){
                   return response(array('message'=>'Wrong token entered!.Please try again'), UNAUTHORIZED)->header('Content-Type', 'application/json');
               }

                if($checktoken->status == 'S'){
            	 return response(array('message'=>'Your account has been suspended by admin, Please contact admin@gmail.com to approve your account'), UNAUTHORIZED)->header('Content-Type', 'application/json'); 
            }
            
            $result_data = array(
                        'user_id' =>$req->input('user_id'),
                        'job_id' =>$req->input('job_id'),
                        'status' =>$req->input('status'),
                        'reasons' =>$req->input('reason')
                         );

          $data_result=DB::table('reasons')->insert($result_data);

          if($data_result){
             return response(array('message'=>'Reason update succssfully'), SUCCESS)->header('Content-Type', 'application/json'); 
            }else{
             return response(array('message'=>'Reason failed to update'), BAD_REQUEST)->header('Content-Type', 'application/json');
            }
        }
   }

/******************************/
/* Get Appoitment details  */
/******************************/
   public function appoitmentDetails($job_id,$user_type,$user_id){

      $rememberToken = $req->header('token');
      $checktoken= $this->GetCheckToken($user_id,$rememberToken);
        if(!$checktoken){
          return response(array('message'=>'Wrong token entered!.Please try again'), UNAUTHORIZED)->header('Content-Type', 'application/json');
        }

         if($checktoken->status == 'S'){
            	 return response(array('message'=>'Your account has been suspended by admin, Please contact admin@gmail.com to approve your account'), UNAUTHORIZED)->header('Content-Type', 'application/json'); 
            }

          $result_data=DB::table('post_services')
              ->where('id',$job_id)
              ->select('services_id','offer_name','offer_price','therapist_gender','massage_length','start_date','start_time','price','street','city','zip','state','parking_instruction')
              ->first();


          $result_data->moreservices=$this->webserviceModel->getServices($result_data->services_id);
          $result_data->userinfo=$this->webserviceModel->getMyProfiles($user_id,$user_type);
 
        if($result_data){
            return response(array('message'=>'Get appoitment details succssfully','data'=>$result_data), SUCCESS)->header('Content-Type', 'application/json');    
        }else{

            return response(array('message'=>'Failed to Get appoitment details'), BAD_REQUEST)->header('Content-Type', 'application/json');    
     }

   }


  /********************************/
  /* Contact us
  /********************************/

     public function contact_us(Request $req)
     {

     $rememberToken = $req->header('token');
     
    $checktoken= $this->GetCheckToken($req->input('user_id'),$rememberToken);
               if(!$checktoken){
                   return response(array('message'=>'Wrong token entered!.Please try again'), UNAUTHORIZED)->header('Content-Type', 'application/json');
               }

                if($checktoken->status == 'S'){
            	 return response(array('message'=>'Your account has been suspended by admin, Please contact admin@gmail.com to approve your account'), UNAUTHORIZED)->header('Content-Type', 'application/json'); 
            }


      $validator = Validator::make($req->all(),[
           'user_id'=>'required',
           'user_name'=>'required',
           'message'=>'required',
           'subject'=>'required',
           'email'=>'required'
    ]);   

     if ($validator->fails()) {
          return response(['message' => $validator->errors()->first()], BAD_REQUEST)->header('Content-Type', 'application/json');

      }else{

            $data = array(
               'user_id'     =>$req->input('user_id'),
               'email'     =>$req->input('email'),
               'user_name'     =>$req->input('user_name'),
               'subject'   =>$req->input('subject'),
               'message'   =>$req->input('message'));

            $result= DB::table('contact_us')->insert($data);
            
            return response(array('message'=>'Message sent successfully'), SUCCESS)->header('Content-Type', 'application/json');  
      }
      
    }

/************************************************/
// Sent Notification
/************************************************/
public function Sent_notification($id,$sender_id,$label,$message,$type=null){

        if($sender_id != $id){

      $datas = DB::table('users')->where('id',$id)->select('device_type','device_id','notification_status')->first();

      if($datas->notification_status == 'ON')
      {
        if($datas->device_id != '')
      {
        if($datas->device_type == 'A')
        {
          
        $insert_id=$this->save_notification($sender_id,$id,$message,$label,$type);
              
        $url = 'https://fcm.googleapis.com/fcm/send';
        $reg = $datas->device_id;
        
        if(!empty($reg)){ 
         $headers = array(
          'Content-Type:application/json',
          'Authorization:key=AIzaSyBFmJUyV1UkM31uVd-hkRkz98jsnAqVtuw'
         );

        //This should be in this format only else it doesn't work and shows no error 
        if($insert_id)
        { 
              $row = array(
                'to' => $reg,
                'data' => array(
                            'id'=>$insert_id,
                            'label'=>$label,
                            'msg' => $message,
                            'sender_id'=>$sender_id,
                            'receiver_id'=>$id
              ));

           
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($row));     
      // print_r($row);die;
        $response = curl_exec($ch);
        
        curl_close($ch);
        }//end of if
  
                     
      }elseif($datas->device_type == 'I'){

          if($datas->device_id != ''){

          

              $insert_id=$this->save_notification($sender_id,$id,$message,$label,$type);

            if($insert_id){
            $row['id'] = $insert_id;
            $row['sender_id'] = $sender_id;
            $row['receicer_id'] = $id;
            $row['message'] = $message;
            $row['label'] = $label;
           
          }

          $passphrase = '';
          $ctx = stream_context_create();
          stream_context_set_option($ctx,'ssl','local_cert','public/appNiff.pem');
          stream_context_set_option($ctx,'ssl','passphrase', $passphrase);

          // Open a connection to the APNS server
                         
          $fp = stream_socket_client('ssl://gateway.sandbox.push.apple.com:2195', $err,$errstr, 60, STREAM_CLIENT_CONNECT|STREAM_CLIENT_PERSISTENT, $ctx);
                                                  //print_r($fp);die;

          if (!$fp)
          exit("Failed to connect: $err $errstr" . PHP_EOL);

          $body['aps'] = array('alert' => $message,'sound' => 'default');
            $body['data']=$row;
                                     // Encode the payload as JSON
          $payload = json_encode($body);

          $msg = chr('o') . pack('n', 32) . pack('H*',$datas->device_id) . pack('n', strlen($payload)) . $payload;

          $dataa = fwrite($fp, $msg, strlen($msg));
                 //print_r($dataa);die;
                        // Save Notifications
          fclose($fp);
               
        }
       }
      } 
      } 
    }
}
   /**************************************************
             Save Notification
    *************************************************/
    function save_notification($sender_id,$receiver_id,$message,$label,$type){

          $notification =  new notification();  
          $notification->sender_id   = $sender_id;
          $notification->receiver_id = $receiver_id;
          $notification->message     = $message;
          $notification->label       = $label;
          $notification->user_type   = $type;
          $notification->save();
          $id = $notification->id;
          return $id;
      }

/****************************/
  /*jobNotification */
/****************************/
public function jobNotification($job_id,$sp_id){
       $data=DB::table('post_services')->where('id',$job_id)->select('user_id','services_id')->get();
       foreach ($data as $k => $value) {
         $moreservices=$this->getOtherServices($value->services_id);
          $data[$k]->more_services = $moreservices;
       }

       $sp_data=DB::table('serviceprovider_profiles')->where('user_id',$sp_id)->select('first_name','last_name')->first();

          $message= $sp_data->first_name.' '.$sp_data->last_name.' assigned  for your booking appoitment';
          $label = "job_forword";
          $type='U';
          $sender_id=$sp_id;

         $this->Sent_notification($data[0]->user_id,$sender_id,$label,$message,$type);
       
       return true;
}


/*********************/
/* Time Ago */
/********************/
   function timeago($time_ago){

    $time_ago = strtotime($time_ago);
    $cur_time   = strtotime(date('Y-m-d H:i:s'));
    $time_elapsed   = $cur_time - $time_ago;
    $seconds    = $time_elapsed ;
    $minutes    = round($time_elapsed / 60 );
    $hours      = round($time_elapsed / 3600);
    $days       = round($time_elapsed / 86400 );
    $weeks      = round($time_elapsed / 604800);
    $months     = round($time_elapsed / 2592000);
    $years      = round($time_elapsed / 31536000);
        
      // Seconds
    if($seconds <= 60){
      return "just now";
    }
    //Minutes
    else if($minutes <=60){
      if($minutes==1){
        return "one min ago";
      }else{
        return "$minutes min ago";
      }
    }
    //Hours
    else if($hours <=24){
      if($hours==1){
        return "an hour ago";
      }else{
        return "$hours hours ago";
      }
    }
      //Days
    else if($days <= 7){
        if($days==1){
        return "yesterday";
        }else{
        return "$days days ago";
        }
    }
        //Weeks
      else if($weeks <= 4.3){
        if($weeks==1){
        return "1 week ago";
        }else{
        return "$weeks weeks ago";
        }
      }
      //Months
    else if($months <=12){
        if($months==1){
        return "1 month ago";
        }else{
        return "$months months ago";
        }
    }
      //Years
      else{
        if($years==1){
        return "1 year ago";
        }else{
        return "$years years ago";
        }
      }
    }  


 /******************************
      Get Notification List
********************************/
    public function get_notification_list($user_id){

         $rememberToken = $req->header('token');
         $checktoken=$this->GetCheckToken($user_id,$rememberToken);

      if(!$checktoken){
          return response(array('message'=>'Wrong token entered!.Please try again'), UNAUTHORIZED)->header('Content-Type', 'application/json');
               }

                if($checktoken->status == 'S'){
            	 return response(array('message'=>'Your account has been suspended by admin, Please contact admin@gmail.com to approve your account'), UNAUTHORIZED)->header('Content-Type', 'application/json'); 
            }

          $get_noti=DB::table('notifications')
             ->where('status','=',1)
             ->where('receiver_id','=',$user_id)
             ->orderBy('id', 'desc')
             ->get();
              
          foreach ($get_noti as $key => $value) {
              $get_noti[$key]->timeago=$this->timeago($value->created_at);
          }

      if($get_noti){
        return response(array('message'=>'Data found','data'=>$get_noti), SUCCESS)->header('Content-Type', 'application/json');    
      }else{
          return response(array('message'=>'Data not found'), BAD_REQUEST)->header('Content-Type', 'application/json');    
        }
  }

 /******************************
      Get review List
********************************/
      function get_review_list(Request $req, $user_id){

        $rememberToken = $req->header('token');
        $checktoken=$this->GetCheckToken($user_id,$rememberToken);
       
        if(!$checktoken){
          return response(array('message'=>'Wrong token entered!.Please try again'), UNAUTHORIZED)->header('Content-Type', 'application/json');
        }
         if($checktoken->status == 'S'){
            	 return response(array('message'=>'Your account has been suspended by admin, Please contact admin@gmail.com to approve your account'), UNAUTHORIZED)->header('Content-Type', 'application/json'); 
            }

          $get_noti=$this->webserviceModel->get_review_lists($user_id);

      if($get_noti){
        return response(array('message'=>'Data found','data'=>$get_noti), SUCCESS)->header('Content-Type', 'application/json');    
      }else{
          return response(array('message'=>'Data not found'), BAD_REQUEST)->header('Content-Type', 'application/json');    
        }
  }

/*---------------------------------
   Update Notification status
-----------------------------------*/
   public function notification_on_off(Request $req){

    $validator = Validator::make($req->all(),[
      'user_id'                  => 'required',
      'notification_status'      => 'required'
    ]);   
    if ($validator->fails()) {      
       
        return response(['message' => $validator->errors()->first()], BAD_REQUEST)->header('Content-Type', 'application/json');
      
      }else{
    
          $rememberToken = $req->header('token');
          $checktoken= $this->GetCheckToken($req->input('user_id'),$rememberToken);
      if(!$checktoken){
          return response(array('message'=>'Wrong token entered!.Please try again'), UNAUTHORIZED)->header('Content-Type', 'application/json');
      }  
       if($checktoken->status == 'S'){
            	 return response(array('message'=>'Your account has been suspended by admin, Please contact admin@gmail.com to approve your account'), UNAUTHORIZED)->header('Content-Type', 'application/json'); 
            }

          $User = new User();
          $User = User::find($req->input('user_id')); 
          $User->notification_status = $req->input('notification_status');

      if($User->save()){  

      if($User->notification_status == 'ON'){
          $notification_status = true;
      }else{
          $notification_status = false;
      }
          return response(array('message'=>'Notifications on Successfully','notification_status'=>$notification_status), SUCCESS)->header('Content-Type', 'application/json');    
      }else{

          return response(array('message'=>'Failed to on Notifications'), BAD_REQUEST)->header('Content-Type', 'application/json');    
     }

     }
   }
/*******************************/
/* upload work picture */
/*******************************/
public function uploadWorkPicture(Request $req){

    If(Input::hasFile('work_picture')){

        $extension = Input::file('work_picture')->getClientOriginalExtension(); 
        $destinationPath = base_path('/') . '/public/uploads/work_picture';
        $profileImage = round(microtime(true)).'.'.$extension;
        Input::file('work_picture')->move($destinationPath, $profileImage); 
        $work_picture['work_picture']=$profileImage;
    }
        $job_id=$req->input('job_id');

        $result_data=DB::table('post_services')->where('id',$job_id)->update($work_picture);

  if($result_data){      
        return response(array('message'=>'work picture save Successfully'), SUCCESS)->header('Content-Type', 'application/json');    
  }else{
        return response(array('message'=>'Faild to save work picture!'), BAD_REQUEST)->header('Content-Type', 'application/json');    
     }
}

/*****************************************/
/* Change Password */
/*****************************************/
 public function change_password(Request $req){
        
      $User= new User;
      $validator = Validator::make($req->all(), [  
        'user_id'       =>  'required',
        'oldPassword'     =>  'required',
        'newPassword'     =>  'required'        
      ]);

      if ($validator->fails()) {        
          return response(['message' => $validator->errors()->first()], BAD_REQUEST)->header('Content-Type', 'application/json');
      }else{

        $User = User::find($req->input('user_id'));
          if($User){

            $rememberToken = $req->header('token');
             $checktoken= $this->GetCheckToken($req->input('user_id'),$rememberToken);
               if(!$checktoken){
                  return response(array('message'=>'Wrong token entered!.Please try again'), UNAUTHORIZED)->header('Content-Type', 'application/json');
               }

                if($checktoken->status == 'S'){
            	 return response(array('message'=>'Your account has been suspended by admin, Please contact admin@gmail.com to approve your account'), UNAUTHORIZED)->header('Content-Type', 'application/json'); 
            }

            if($User->registration_type != 'O'){
                return response(array('message'=>'Sorry, This account has  been linked through social media account'), NOT_ACCEPTABLE)->header('Content-Type', 'application/json');
            }
            if (!Hash::check(Input::get('oldPassword'), $User->password)) {

                return response(array('message'=>'Your old password does not match'), NOT_ACCEPTABLE)->header('Content-Type', 'application/json');
            }
              $User->password = Hash::make($req->input('newPassword'));
              $User->save();
              if($User->id){
                return response(array('message'=>'Password has been Changed Successfully'), SUCCESS)->header('Content-Type', 'application/json'); 
              } else {
                
              return response(array('message'=>'Failed to change password!'), BAD_REQUEST)->header('Content-Type', 'application/json');
              }
          } else {
              return response(array('message'=>'User id not exist'), NOT_FOUND)->header('Content-Type', 'application/json');
            }   
         }
    } 

/******/ 
    }
/******/