<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tymon\JWTAuth\Exceptions\JWTException;
use App\Models\OtpVerification;
use App\Mail\Register;
use App\Models\User;
use App\Jobs\UserCreated;
use App\Models\Models\Order;
use App\Models\Models\Quote;
use Illuminate\Support\Facades\Hash;
use App\Models\Models\RegistrationLog;
use App\ServicesMy\MailService;
use JWTAuth;
use Validator;
use Response;
use Mail;
use DB;
use Nullix\CryptoJsAes\CryptoJsAes;

class DashboardController extends Controller
{
    /**
    * Display a listing of the resource.
    *
    * @return \Illuminate\Http\Response
    */
   public function userDashboard(Request $request)
   {
   	  
   		$userid = $request->user_id;

   		$getuser = User::where('id',$userid)->first();

   		// dd($getuser);
   		// C -- Customer
   		// Kam -- cam
   		if ( date('m') <= 03 ) {
   		 		$preyear = date("Y",strtotime("-1 year"));
   		 		$fromdate = $preyear.'-'.'04'.'-'.'01';
			    $todate = date("Y-m-d");
			}
			else {
				$year = date("Y");
			    $fromdate = $year.'-'.'04'.'-'.'01';
			    $todate = date("Y-m-d");
			}
   		 
   		 if ($getuser->user_type == 'C') {
   		 	$quote = DB::table('orders')
            ->leftjoin('quotes','orders.rfq_no','quotes.rfq_no')            
            ->where('quotes.user_id',$userid)
            ->whereNull('quotes.deleted_at')
            ->count();
	        $data['total_no_of_orders'] = $quote;

	         $rfqNego = DB::table('quotes') 
	            ->where('quotes.user_id',$userid)
	            ->where('quotes.kam_status',6)
	            ->whereNull('quotes.deleted_at')
	            ->count();
	        $data['rfq_under_negotiation'] = $rfqNego;

	        $orderCon = DB::table('orders')
	            ->leftjoin('quotes','orders.rfq_no','quotes.rfq_no')             
	            ->where('quotes.user_id',$userid)
	            ->where('orders.status',1)
	            ->whereNull('quotes.deleted_at')
	            ->count();
	        $data['orders_confirmed_pending_for_delivery'] = $orderCon; 
	       

	        $custComplain = DB::table('complain_main') 
	            ->where('complain_main.user_id',$userid)
	            ->where('complain_main.closed_status',1) 
	            ->count();

	        $data['Total_no_of_open_complaints'] = $custComplain;
   		 }
   		 else if ($getuser->user_type == 'Kam') {
   		 	$quote = DB::table('orders')
            ->leftjoin('quotes','orders.rfq_no','quotes.rfq_no')  
            ->leftjoin('users','quotes.user_id','users.id')           
            ->where('users.zone',$getuser->zone)
            ->whereNull('quotes.deleted_at')
            ->count();
	        $data['total_no_of_orders'] = $quote;

	        $userzone = DB::table('users')  
	            ->where('users.zone',$getuser->zone)
	            ->where('users.user_type','C') 
	            ->count();
	             
	        $data['total_no_cust_assinged'] = $userzone;
	        // dd($getuser->zone);
	        $orderCon = DB::table('orders')
	            ->leftjoin('quotes','orders.rfq_no','quotes.rfq_no')             
	            ->leftjoin('users','quotes.user_id','users.id')
	            ->where('orders.status',1)
	            ->where('users.zone',$getuser->zone)
	            ->whereNull('quotes.deleted_at')
	            ->count();
	        $data['orders_confirmed_pending_for_delivery'] = $orderCon;

	        $rfqNego = DB::table('quotes') 
	            ->leftjoin('users','quotes.user_id','users.id')
	            ->where('quotes.kam_status',6)
	            ->where('users.zone',$getuser->zone)
	            ->whereNull('quotes.deleted_at')
	            ->count();
	        $data['rfq_under_negotiation'] = $rfqNego;

	        $custComplain = DB::table('complain_main') 
	        	->leftjoin('users','complain_main.user_id','users.id')
	            ->where('users.zone',$getuser->zone)
	            ->where('complain_main.closed_status',1) 
	            ->count();

	        $data['Total_no_of_open_complaints'] = $custComplain;


	        // ---------------- top 5 ytd cus ---------------------------
            $ytddata = array();
	        $ytd = DB::table('orders')
	               ->leftjoin('quotes','orders.rfq_no','quotes.rfq_no')
	               ->leftjoin('users','quotes.user_id','users.id')
	               ->leftjoin('quote_schedules','quotes.id','quote_schedules.quote_id')
	               ->select('users.org_name','quote_schedules.quantity',DB::raw("SUM(quote_schedules.quantity) as qtycount"))
	               ->orderBy('qtycount', 'DESC')
	               ->limit(5)
	               ->where('users.zone',$getuser->zone)
	               ->where('orders.status',1)->get();
            foreach ($ytd as $key => $value) {
            	  $ytddata[$key]['org_name'] = $value->org_name;
            	  $ytddata[$key]['qtycount'] = $value->qtycount;
            }
	            
	          $data['top_five_cust_sale'] = $ytddata;  
	        // ----------------------------------------------------------
   		 }
   		 else if ($getuser->user_type == 'Sales' || $getuser->user_type == 'SM') { 

   		 	// Show data according to financial year.....
   		 	$volumeCon = DB::table('quotes')
   		 	 	->select('quantity') 
	            ->where('quotes.kam_status',4)
	            ->where('quotes.created_at','>=', $fromdate)
                ->where('quotes.created_at','<=', $todate) 
	            ->whereNull('quotes.deleted_at') 
	            ->sum('quotes.quantity');	         
	        $data['volumeconfirmed'] = $volumeCon;

	        $volumeUnderNego = DB::table('quotes')
   		 	 	->select('quantity') 
	            ->where('quotes.kam_status',6) 
	            ->where('quotes.created_at','>=', $fromdate)
                ->where('quotes.created_at','<=', $todate)
	            ->whereNull('quotes.deleted_at')
	            // ->groupBy('rfq_no')
	            ->sum('quotes.quantity');	         
	        $data['volume_under_negotiation'] = $volumeUnderNego;
 			

	        $getrfqno = DB::table('quotes')
	        	->select('quotes.id')
	            ->where('quotes.kam_status',4) 
	            ->where('quotes.created_at','>=', $fromdate)
                ->where('quotes.created_at','<=', $todate) 
	            ->whereNull('quotes.deleted_at')
	            ->groupBy('rfq_no')
	            ->get(); 
	             $explantconordersum = 0;
	            foreach ($getrfqno as $key => $valuesum) 
	            {
	            	$getqutsedno = DB::table('quote_schedules') 
	            	->where('quote_schedules.quote_id',$valuesum->id)
		            ->where('quote_schedules.pickup_type','=','PLANT') 
		            ->get(); 
		            
		            foreach ($getqutsedno as $key => $sumofqua) {
		            	 
		            	$explantconordersum+= $sumofqua->quantity;
		            }	            	
	            }
	            
	            	         
	        $data['ex_plant_confirmed_orders'] = $explantconordersum;

	        $getdepotrfqno = DB::table('quotes')
	        	->select('quotes.id')
	            ->where('quotes.kam_status',4)
	            ->where('quotes.created_at','>=', $fromdate)
                ->where('quotes.created_at','<=', $todate)
	            ->whereNull('quotes.deleted_at')
	            ->groupBy('rfq_no')
	            ->get(); 
	            $exdepotconordersum = 0;
	            foreach ($getdepotrfqno as $key => $valsum) 
	            {
	            	$getdepotrfq = DB::table('quote_schedules') 
	            	->where('quote_schedules.quote_id',$valsum->id)
		            ->where('quote_schedules.pickup_type','=','DEPOT') 
		            ->get(); 
		            
		            foreach ($getdepotrfq as $key => $sumofqua) {
		            	 
		            	$exdepotconordersum+= $sumofqua->quantity;
		            }	            	
	            }
	            
	            	         
	        $data['ex_Depot_confirmed_orders'] = $exdepotconordersum;

	        $getdaprfq = DB::table('quotes')
	        	->select('quotes.id')
	            ->where('quotes.kam_status',4)  
	            ->where('quotes.created_at','>=', $fromdate)
                ->where('quotes.created_at','<=', $todate)
	            ->whereNull('quotes.deleted_at')
	            ->groupBy('rfq_no')
	            ->get(); 
	            $dapconordersum = 0;
	            foreach ($getdaprfq as $key => $valdapsum) 
	            {
	            	$getnewdaprfq = DB::table('quote_schedules') 
	            	->where('quote_schedules.quote_id',$valdapsum->id)
		            ->where('quote_schedules.delivery','=','DAP (Delivered at Place)') 
		            ->get(); 
		            
		            foreach ($getnewdaprfq as $key => $sumofdapqua) {
		            	 
		            	$dapconordersum+= $sumofdapqua->quantity;
		            }	            	
	            }
	            
	            	         
	        $data['DAP_confirmed_orders'] = $dapconordersum;

	        // End of Show data according to financial year.....



	        // Show data according to month .....
	         

	        $fromdatem = date("Y").'-'.date('m').'-'.'01';
			$todatem = date("Y-m-d");
	         
	         

	         
 			

	        $getrfqno = DB::table('quotes')
	        	->select('quotes.id')
	            ->where('quotes.kam_status',4) 
	            ->where('quotes.created_at','>=', $fromdatem)
                ->where('quotes.created_at','<=', $todatem) 
	            ->whereNull('quotes.deleted_at')
	            ->groupBy('rfq_no')
	            ->get(); 
	             $explantconordersum = 0;
	            foreach ($getrfqno as $key => $valuesum) 
	            {
	            	$getqutsedno = DB::table('quote_schedules') 
	            	->where('quote_schedules.quote_id',$valuesum->id)
		            ->where('quote_schedules.pickup_type','=','PLANT') 
		            ->get(); 
		            
		            foreach ($getqutsedno as $key => $sumofqua) {
		            	 
		            	$explantconordersum+= $sumofqua->quantity;
		            }	            	
	            }
	            
	            	         
	        $data['ex_plant_con_orders_chrt_mon'] = $explantconordersum;

	        $getdepotrfqno = DB::table('quotes')
	        	->select('quotes.id')
	            ->where('quotes.kam_status',4)
	            ->where('quotes.created_at','>=', $fromdatem)
                ->where('quotes.created_at','<=', $todatem)
	            ->whereNull('quotes.deleted_at')
	            ->groupBy('rfq_no')
	            ->get(); 
	            $exdepotconordersum = 0;
	            foreach ($getdepotrfqno as $key => $valsum) 
	            {
	            	$getdepotrfq = DB::table('quote_schedules') 
	            	->where('quote_schedules.quote_id',$valsum->id)
		            ->where('quote_schedules.pickup_type','=','DEPOT') 
		            ->get(); 
		            
		            foreach ($getdepotrfq as $key => $sumofqua) {
		            	 
		            	$exdepotconordersum+= $sumofqua->quantity;
		            }	            	
	            }
	            
	            	         
	        $data['ex_Depot_con_orders_chrt_mon'] = $exdepotconordersum;

	        $getdaprfq = DB::table('quotes')
	        	->select('quotes.id')
	            ->where('quotes.kam_status',4)  
	            ->where('quotes.created_at','>=', $fromdatem)
                ->where('quotes.created_at','<=', $todatem)
	            ->whereNull('quotes.deleted_at')
	            ->groupBy('rfq_no')
	            ->get(); 
	            $dapconordersum = 0;
	            foreach ($getdaprfq as $key => $valdapsum) 
	            {
	            	$getnewdaprfq = DB::table('quote_schedules') 
	            	->where('quote_schedules.quote_id',$valdapsum->id)
		            ->where('quote_schedules.delivery','=','DAP (Delivered at Place)') 
		            ->get(); 
		            
		            foreach ($getnewdaprfq as $key => $sumofdapqua) {
		            	 
		            	$dapconordersum+= $sumofdapqua->quantity;
		            }	            	
	            }
	            
	            	         
	        $data['DAP_con_orders_chrt_mon'] = $dapconordersum;

	        // End of Show data according to month .....

   		 }

   		 $data['mtdata'] = 'MT';
   		
         
        // $password = "123456";
        // $encrypted = CryptoJsAes::encrypt($data, $password);
            
        return response()->json(['status'=>1,'message' =>'success.','result' => $data],200);
   }	
}
