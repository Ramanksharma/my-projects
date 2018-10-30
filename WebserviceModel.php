<?php
namespace App\v1;

use File;
use DB;
use Mail;
use Auth;
use fileupload\FileUpload;
use Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;

class WebserviceModel extends Model 
{

	/*********************************
    // Get Single User
    *********************************/
    public function getusers($user_id)
    {
	     $getUsers=DB::table('users')
	              ->where('id',$user_id)
	              ->select('*')
	              ->first();

	     return $getUsers;
    }
	/***********************************/
	// Update login token
	/***********************************/

    public function UpdateLoginToken($id,$token_key,$deviceType,$deviceId){
            $UpdateDetailObj = DB::table('users')
						->where('id',$id)
						->limit(1)  
						->update(array('token' =>$token_key,'device_id'=>$deviceId,'device_type'=>$deviceType));

		return $UpdateDetailObj;
}


	/***********************************/
	// Get Password Token 
	/***********************************/
	public function GetPasswordToken($password_token,$user_id){

           $result_data = DB::table('users')
			  ->where('password_token',$password_token)
			  ->where('id',$user_id)
			  ->select('*')
			  ->first();

			  return $result_data;

	}


/***********************************/
/* Insert Data */
/***********************************/
  Public function insertdata($tablename,$data){
  	$result=DB::table($tablename)->insert($data);
    return $result;
  }
  /***********************************/
/* update Data */
/***********************************/
  Public function addprofile($tablename,$data){
  	$result=DB::table($tablename)->insert($data);
    return $result;
  }
/***********************************/
/* Get Data */
/***********************************/
/*  Public function getdata($tablename,$id){
  	$result=DB::table($tablename)->where('service_id',$id)->select('*');
    return $result;
  }*/

/************************************/
/* Check Connection */
/**************************************/    
 
  public function checkConnection($sender_id,$receiver_id,$job_id){
     $checkConnection = DB::table('connection')
        ->where('job_id',$job_id)
        ->where([['sender_id','=',"$sender_id"],['reciver_id','=',"$receiver_id"]])
        ->orwhere([['sender_id','=',"$receiver_id"],['reciver_id','=',"$sender_id"]])
        ->first();
     return  $checkConnection;
  }
 

 /**********************************/
  /* Add Connection  */
  /**********************************/
   public function add_connection($sender_id,$receiver_id,$job_id){
            $addConnection = DB::table('connection')->insert(['sender_id'=>$sender_id,'reciver_id'=>$receiver_id,'del'=>'OFF','job_id'=>$job_id]);
         }



  /**********************************/
  /* Insert Message */
  /***********************************/
     public function insert_message($sender_id,$message,$connection_id){
            $addConnection = DB::table('conversation')
                             ->insert([
                             	   'user_id'=>$sender_id,
                                 'connection_id'=>$connection_id,
                             	   'del'=>'OFF',
                             	   'message'=>$message
                             	   ]);
     }

   /********************************/
  /*Get user info*/
  /*******************************/
     public function getUserInfo($user_id){
     
       $result=DB::table('users')->where('id',$user_id)->select('user_name','profile_picture')->first();
      
      return $result;
    }

/******************************/
/*get Conversation data*/
/******************************/
    public function getdata($tblname,$conversation_id,$user_id){
	      $result=DB::table($tblname)->where(['id'=>$conversation_id])->select('*')->first();
	       return $result;
    }

/******************************/
/*get cinnection data*/
/******************************/
    public function getchatdata($tblname,$connection_id,$user_id){
        $result=DB::table($tblname)->where(['connection_id'=>$connection_id])->select('*')->first();
         return $result;
    }
/****************************/
/* Get My Profile */
/****************************/
public function getMyProfiles($user_id,$user_type){
    
    if($user_type=='SP'){

      $result_data = DB::table('serviceprovider_profiles as sp')
              ->leftJoin('feedbacks as fd','sp.user_id','=','fd.ratable_id')
              ->where('sp.user_id',$user_id)
              ->select('sp.first_name','sp.last_name','sp.address','sp.mobnumber','sp.gender','sp.profile_picture','sp.user_id','sp.about','sp.experience','sp.services',DB::raw("(select case WHEN avg(rating) IS NULL THEN 0 ELSE avg(rating) END AS rating from feedbacks where ratable_id=sp.user_id) as user_rating,CASE WHEN sp.profile_picture is NULL OR sp.profile_picture = '' THEN CONCAT('',sp.profile_picture) ELSE CONCAT('".url('/').'/public/uploads/profile/'."',sp.profile_picture) END AS profile_picture,CASE WHEN sp.id_card is NULL OR sp.id_card = '' THEN CONCAT('',sp.id_card) ELSE CONCAT('".url('/').'/public/uploads/idCard/'."',sp.id_card) END AS id_card,(Select count(comments) from feedbacks where ratable_id=sp.user_id ) as reviews"))
              ->get();

      foreach ($result_data as $k => $value) 
      {
        $moreservices=$this->getMyServices($value->services);           
        $result_data[$k]->services = $moreservices;
      }

    }else{

      $result_data=DB::table('user_profiles as up')   
      ->leftJoin('feedbacks as fd','up.user_id','=','fd.ratable_id')
      ->where('up.user_id',$user_id)
      ->select('up.first_name','up.last_name','up.address','up.mobnumber','up.gender','up.profile_picture','up.user_id',DB::raw("(select case WHEN avg(rating) IS NULL THEN 0 ELSE avg(rating) END AS rating from feedbacks where ratable_id=up.user_id) as user_rating,CASE WHEN up.profile_picture is NULL OR up.profile_picture = '' THEN CONCAT('',up.profile_picture) ELSE CONCAT('".url('/').'/public/uploads/profile/'."',up.profile_picture) END AS profile_picture,(Select count(comments) from feedbacks where ratable_id=up.user_id ) as reviews"))
     ->first();
      
      }
   
   return $result_data;
}

/*****************************/
/**/
/*****************************/

 Public function getMyServices($data){
       $services_id=explode(',', $data);
       $result_data= array();
       foreach ($services_id as $key => $value) {
             $result=DB::table('services_list')
                         ->where('id',$value)
                         ->select('id','title')
                         ->first();                         
            if($result){
              $result_data[]=$result;
        }
    }

           return $result_data;
   }

   /*********************************/
   /**updateProfile/
   /*********************************/

public function updateProfile($tblname,$service_provider,$user_id){
     $data=DB::table($tblname)
          ->where('user_id',$user_id)
          ->update($service_provider);
    return $data;
}

/************************************/
/* Get Services */
/*************************************/
public function getServices($data){
       $services_id=explode(',', $data);
       $result_data= array();
       foreach ($services_id as $key => $value) {
             $result=DB::table('services_list')
                         ->where('id',$value)
                          ->select('title',DB::raw("CASE WHEN service_image is NULL OR service_image = '' THEN CONCAT('',service_image) ELSE CONCAT('".url('/').'/public/uploads/services_images/'."',service_image) END AS service_image"))
                         ->first();                         
            if($result){
              $result_data[]=$result;
        }
    }

           return $result_data;
   }

/***********************************/
/*Get FeedBack*/
/***********************************/
public function getFeedBack($job_id,$user_id){
$result_data=DB::table('feedbacks')->where('job_id',$job_id)->where('rater_id',$user_id)->select('rating','comments')->first();
if(!empty($result_data)){
  return $result_data;
}
return false;
}

/*********************/
/* user home page  */
/*********************/
public function userHome(){
$result_data=DB::table('services_list as sl')
                 ->select('sl.id as services_id','sl.title','sl.service_image','sl.description',DB::raw("CASE WHEN sl.service_image is NULL OR sl.service_image = '' THEN CONCAT('',sl.service_image) ELSE CONCAT('".url('/').'/public/uploads/services_images/'."',sl.service_image) END AS service_image"))->get();

                foreach ($result_data as $k => $value) {
                   $results=DB::table('service_length_time')->where(['service_id'=>$value->services_id])->select('service_length','price')->get();
                   $result_data[$k]->service_length_time = $results;
                }
return $result_data;
}
/*****************************/
/* Get Review List */
/****************************/
public function get_review_lists($user_id){
    $get_noti=DB::table('feedbacks as fb')
          ->join('users as u','u.id','=','fb.ratable_id')
          ->where('ratable_id','=',$user_id)
          ->select('u.user_name','u.profile_picture','fb.*')
          ->get();
   
        foreach ($get_noti as $key => $value) {
            $get_noti[$key]->timeago=$this->timeago($value->created_at);

                if($value->profile_picture){
                      $get_noti[$key]->profile_picture=URL('/').'/public/uploads/profile/'.$value->profile_picture;
                }else{
                      $get_noti[$key]->profile_picture="";
                }
          }

          return $get_noti;
}


/*****/
  }
/*****
