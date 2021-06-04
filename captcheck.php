<?php
/*
 * This is a PHP library that handles calling Captcheck.
 *    - Documentation and latest version
 *          https://source.netsyms.com/Netsyms/Captcheck/
 *    - Live demo 
 *          https://captcheck.netsyms.com/
 *
 * Copyright (C) 2017-2019 Netsyms Technologies.
 * 
 * Permission is hereby granted, free of charge, to any person obtaining a copy of
 * this software and associated documentation files (the "Software"), to deal in
 * the Software without restriction, including without limitation the rights to
 * use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of
 * the Software, and to permit persons to whom the Software is furnished to do so,
 * subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS
 * FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL
 * NETSYMS TECHNOLOGIES BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
 * WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN
 * CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 * 
 * Except as contained in this notice, the name and other identifying marks of
 * Netsyms Technologies shall not be used in advertising or otherwise to promote
 * the sale, use or other dealings in this Software without prior written
 * authorization from Netsyms Technologies.
 */

/***
  * Check Captcheck's response
  */
  
function captcheck_check_answer ($ccsessioncode, $ccanswer)
{
	
		/***
		 * Get session code and answer from the post
		 */

		$data = array (
					'session_id' => $ccsessioncode,
					'answer_id' => $ccanswer,
					'action' => "verify"
				);

		/**
		 * Build query with the session ID and answer 
		 */
		 
		$httpopt = array (
						'header' => "Content-type: application/x-www-form-urlencoded\r\n",
						'method' => 'POST',
						'content' => http_build_query($data)
		);

		$options = array (
				'http' => $httpopt
		);


		$context = stream_context_create($options);
		$result = file_get_contents(KU_CAPTCHECK_API_SERVER, false, $context);
		$resp = json_decode($result, TRUE);
				
		/**
		 * Finally, validate Captcheck's response
		 */
	
		if (!$resp['result']) {
				$captcheck_response->is_valid = false; 
		} else {
				$captcheck_response->is_valid = true;
		}
        return $captcheck_response;

}

?>
