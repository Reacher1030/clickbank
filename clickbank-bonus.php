<?php 

/* 
 * This is a PHP script that processes ClickBank Order Numbers to redirect your 
 * real customers to the page with your bonuses. 
 *    - Documentation and latest version 
 *          http://www.cbgraph.com/articles/clickbank-bonus-script.html 
 * 
 * Copyright (c) 2011 CBGraph -- http://www.cbgraph.com 
 * AUTHOR: 
 *   Sergey Korchan 
 * VERSION: 
 *   1.0 (2011-12-23) 
 * 
 * Permission is hereby granted, free of charge, to any person obtaining a copy 
 * of this software and associated documentation files (the "Software"), to deal 
 * in the Software without restriction, including without limitation the rights 
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell 
 * copies of the Software, and to permit persons to whom the Software is 
 * furnished to do so, subject to the following conditions: 
 * 
 * The above copyright notice and this permission notice shall be included in 
 * all copies or substantial portions of the Software. 
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR 
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, 
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE 
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER 
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, 
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN 
 * THE SOFTWARE. 
 */ 

define('CB_DEVELOPER_KEY',    'DEV-XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX');    // Your ClickBank Developer Key. 
define('CB_CLERK_KEY',        'API-XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX');    // Your Clerk Key (with the 'api_order_read' role). 

define('SUCCESS_URL', 'http://www.YourDomain.com/success.html');    // URL of your page with bonuses. 


foreach ($_POST as $k => $v) 
{ 
    $$k = trim(strip_tags($v)); 
} 

if ($receipt != "") 
{ 
    define('VENDOR_ID', '');        // Comma-separated list of acceptable vendors (e.g. 'SOMEVENDOR' or 'ABCDE,VENDORX,MOREONE'). Leave it empty if any vendor is acceptable. 
    define('ERROR_ON_REFUND', 1);    // Show an error if the purchase has been refunded (1 or 0). 
    define('ERROR_ON_CANCEL', 0);    // Show an error if the subscription (for recurring billing products only) has been canceled (1 or 0).



    // ERROR DEFINITIONS 
    define('ERROR_MSG_EMPTY_RECEIPT',            "You have not provided any ClickBank Order Number."); 
    define('ERROR_MSG_INVALID_RECEIPT',            "The provided ClickBank Order Number is not valid."); 
    define('ERROR_MSG_COULD_NOT_CONNECT',        "Oops! Our system could not connect to ClickBank's servers. Please, try again later!");
    define('ERROR_MSG_BAD_RECEIPT',                "We could not find such ClickBank Order Number. Make sure you have purchased the product through our affiliate link.");
    define('ERROR_MSG_PARSE_ERROR',                "Oops! Our system could not parse the result from ClickBank's servers."); 
    define('ERROR_MSG_NO_ORDERDATA',            "Oops! The 'orderData' property doesn't exist."); 
    define('ERROR_MSG_INAPPROPRIATE_VENDOR',    "We don't provide any bonus for the purchased product."); 
    define('ERROR_MSG_REFUNDED',                "Your payment has been refunded."); 
    define('ERROR_MSG_CANCELED',                "Your subscription has been canceled."); 



    // Gets the Receipt# from the request. 
    $receipt = isset($_REQUEST['receipt']) ? strtoupper(trim($_REQUEST['receipt'])) : ''; 
    if (substr($receipt, 0, 1) == '#') { 
        $receipt = trim(substr($receipt, 1)); // Truncates the first character '#'. 
    } 
    if (preg_match('|^([0-9A-Z]*)[-]|', $receipt, $matches)) { 
        $receipt = $matches[1];    // Truncates the '-Bddd' part (for example, FJ8SLJE7-B003 => FJ8SLJE7). 
    } 
    // Parses the acceptable vendors list. 
    $acceptable_vendors = array(); 
    $arr = explode(',', VENDOR_ID); 
    foreach($arr as $vendor_id) { 
        $vendor_id = trim($vendor_id); 
        if (preg_match('|^[0-9A-Z]{5,10}$|i', $vendor_id)) { 
            $acceptable_vendors[] = strtoupper($vendor_id); 
        } 
    } 

    $err = false; 

    // Checks the format of the Receipt# 
    if (!$err && $receipt == '') { 
        $err = ERROR_MSG_EMPTY_RECEIPT; 
    } 
    if (!$err && !preg_match('|^[0-9A-Z]{8,9}$|', $receipt)) { 
        $err = ERROR_MSG_INVALID_RECEIPT; 
    } 

    if (!$err) { 
        $ch = curl_init(); 

        // Connects to the ClickBank Orders Service API 
        curl_setopt($ch, CURLOPT_URL, 'https://api.clickbank.com/rest/1.3/orders/' . $receipt); 
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
        curl_setopt($ch, CURLOPT_HEADER, false); 
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
        curl_setopt($ch, CURLOPT_HTTPHEADER, array( 
            'Accept: application/json', 
            'Authorization: ' . CB_DEVELOPER_KEY . ':' . CB_CLERK_KEY 
        )); 
        $result = curl_exec($ch); 
        if ($result === FALSE) { // Couldn't connect. 
            $err = ERROR_MSG_COULD_NOT_CONNECT; 
        } else { 
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); // Code is 200 when the Receipt# belongs to your account. 
            if ($code != 200) { 
                $err = ERROR_MSG_BAD_RECEIPT; 
            } 
        } 
        curl_close($ch); 

        // Parses the recieved JSON result. 
    @    $o = json_decode($result); 
        if (!$err && !is_object($o)) { 
            $err = ERROR_MSG_PARSE_ERROR; 
        } 
        if (!$err && !property_exists($o, 'orderData')) { 
            $err = ERROR_MSG_NO_ORDERDATA; 
        } 

        // Checks the orderData. 
        if (!$err) { 
            $data = is_array($o->orderData) ? $o->orderData : array($o->orderData); 

            $refunded = false; 
            foreach($data as $record) { 
                if ($record->txnType == 'RFND') { 
                    $refunded = true; 
                } 
            } 

            $canceled = (is_string($data[0]->status) && $data[0]->status == 'CANCELED'); 

            $site = strtoupper($data[0]->site); 
            $site_is_ok = sizeof($acceptable_vendors) == 0 ? true : in_array($site, $acceptable_vendors); 

            if (!$site_is_ok) { 
                $err = ERROR_MSG_INAPPROPRIATE_VENDOR; 
            } else if ($refunded && ERROR_ON_REFUND) { 
                $err = ERROR_MSG_REFUNDED; 
            } else if ($canceled && ERROR_ON_CANCEL) { 
                $err = ERROR_MSG_CANCELED; 
            } 
        } 

    } 

    if ($err) { 
         
    } else { 
        header('Location: ' . SUCCESS_URL); 
    } 
}     

?> 
<!DOCTYPE html> 
<html> 
<head> 
<meta charset="utf-8"> 
<meta http-equiv="X-UA-Compatible" content="IE=edge"> 
<title>ClickBank</title> 
<meta name="robots" content="noindex,nofollow" /> 
<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.2.0/css/bootstrap.min.css"> 
<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.2.0/css/bootstrap-theme.min.css"> 
<script src="http://ajax.googleapis.com/ajax/libs/webfont/1/webfont.js" type="text/javascript"></script> 
<meta name="viewport" content="width=device-width, initial-scale=1.0"> 
</head> 
<body> 
<div id="wrap"> 
    <div class="container"> 
        <?php if ($err != "") echo "<p>".$err."</p>\n"; ?> 
        <form class="form-horizontal" method="POST" action=""> 
            <div class="form-group"> 
                <label for="receipt" class="col-sm-2 control-label">Order Number</label> 
                <div class="col-sm-10"> 
                    <input type="text" class="form-control" name="receipt" id="receipt" placeholder="" value=""> 
                </div> 
            </div> 
            <div class="form-group"> 
                <div class="col-sm-offset-2 col-sm-10"> 
                    <button type="submit" class="btn btn-default">Verify</button> 
                </div> 
            </div> 
          </form> 
    </div>     
</div>         
</body> 
</html>