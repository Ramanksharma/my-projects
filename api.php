<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});


	Route::get('/webservice', function () {
        return view('v1.webservices');
    });
Route::get('/view', function () {
        return view('v1.view');
    });



Route::group(['prefix' => ''], function() {
	// Response Status Code //
	define('SUCCESS', '200');
	define('NO_CONTENT', '204');
	define('UNAUTHORIZED', '401');
	define('BAD_REQUEST', '400');
	define('NOT_FOUND', '404');
	define('UPGRADE', '426');
	define('NOT_ACCEPTABLE', '406');
	define('CONFLICT', '409');
});




Route::post('webservices/signup','v1\Usercontroller@signup')->name('signup');
Route::any('webservices/login','v1\Usercontroller@login')->name('login');
Route::any('webservices/forgotPassword','v1\Usercontroller@forgotPassword')->name('forgotPassword');
Route::get('resetPasswordForm/{token}','v1\Usercontroller@resetPasswordForm')->name('resetPasswordForm');
Route::any('webservices/setNewPassword','v1\Usercontroller@setNewPassword')->name('setNewPassword');
Route::post('webservices/createProfile','v1\Usercontroller@createProfile')->name('createProfile');
Route::post('webservices/addcard','v1\Usercontroller@addcard')->name('addcard');
Route::post('webservices/addBank','v1\Usercontroller@addBank')->name('addBank');
Route::get('webservices/userHome','v1\Usercontroller@userHome')->name('userHome');
Route::any('webservices/services_details','v1\Usercontroller@services_details')->name('services_details');
Route::post('webservices/post_service','v1\Usercontroller@addNewService')->name('addNewService');
Route::post('webservices/getConfirmPost','v1\Usercontroller@getConfirmPost')->name('getConfirmPost');
Route::post('webservices/addMoreServices','v1\Usercontroller@addMoreServices')->name('addMoreServices');


Route::post('webservices/addServicesAdmin','v1\Usercontroller@addServicesAdmin')->name('addServicesAdmin');
Route::post('webservices/send_message','v1\Usercontroller@send_message')->name('send_message');
Route::get('webservices/getMyConnection/{user_id}','v1\Usercontroller@getMyConnection')->name('getMyConnection');
Route::get('webservices/getMychat/{user_id}/{connection_id}','v1\Usercontroller@getMychat')->name('getMychat');
Route::post('webservices/giveFeedback','v1\Usercontroller@giveFeedback')->name('giveFeedback');
Route::get('webservices/getMyProfile/{user_id}/{user_type}','v1\Usercontroller@getMyProfile')->name('getMyProfile');
Route::post('webservices/editMyProfile','v1\Usercontroller@editMyProfile')->name('editMyProfile');
Route::get('webservices/getHistory/{user_id}/{user_type}/{status}','v1\Usercontroller@getHistory')->name('getHistory');
Route::get('webservices/spHome/{user_id}','v1\Usercontroller@spHome')->name('spHome');
Route::post('webservices/updateJobStatus','v1\Usercontroller@updateJobStatus')->name('updateJobStatus');
Route::post('webservices/CancelRejectJob','v1\Usercontroller@CancelRejectJob')->name('CancelRejectJob');
Route::get('webservices/appoitmentDetails/{job_id}/{user_type}/{user_id}','v1\Usercontroller@appoitmentDetails')->name('appoitmentDetails');
Route::post('webservices/contact_us','v1\Usercontroller@contact_us')->name('contact_us');
Route::post('webservices/deleteconnection','v1\Usercontroller@deleteconnection')->name('deleteconnection');
Route::post('webservices/logout','v1\Usercontroller@logout')->name('logout');

Route::get('webservices/get_notification_list/{user_id}','v1\Usercontroller@get_notification_list')->name('get_notification_list');
Route::get('webservices/get_review_list/{user_id}','v1\Usercontroller@get_review_list')->name('get_review_list');
Route::post('webservices/notification_on_off','v1\Usercontroller@notification_on_off')->name('notification_on_off');
Route::post('webservices/uploadWorkPicture','v1\Usercontroller@uploadWorkPicture')->name('uploadWorkPicture');
Route::post('webservices/change_password','v1\Usercontroller@change_password')->name('change_password');








Route::post('webservices/card_save','v1\Paymentcontroller@card_save')->name('card_save');
Route::get('webservices/cards_list/{user_id}','v1\Paymentcontroller@cards_list')->name('cards_list');
Route::post('webservices/payment','v1\Paymentcontroller@payment')->name('payment');
Route::post('webservices/cards_delete','v1\Paymentcontroller@cards_delete')->name('cards_delete');
Route::post('webservices/addbankaccount','v1\Paymentcontroller@addBankAccount')->name('addBankAccount');
Route::get('webservices/add_Bank_Details','v1\Paymentcontroller@add_Bank_Details')->name('add_Bank_Details');