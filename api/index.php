<?php
error_reporting(0);

if ($_FILES['webshot_file']['error'] == 0) {

    $fileName = $_FILES['webshot_file']['name'];
    $fileTmpName = $_FILES['webshot_file']['tmp_name'];

    // Read the file contents and encode it to base64
    $fileContent = file_get_contents($fileTmpName);
    $base64Data = base64_encode($fileContent);

    // Set cURL options for upload image
    $curl = curl_init();

	curl_setopt_array($curl, array(
	  CURLOPT_URL => 'https://api.imgbb.com/1/upload?expiration=600&key=861cd52d76fb5898ef55423307677a39',
	  CURLOPT_RETURNTRANSFER => true,
	  CURLOPT_ENCODING => '',
	  CURLOPT_MAXREDIRS => 10,
	  CURLOPT_TIMEOUT => 0,
	  CURLOPT_FOLLOWLOCATION => true,
	  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
	  CURLOPT_CUSTOMREQUEST => 'POST',
	  CURLOPT_POSTFIELDS => array('image' => $base64Data),
	));

	$response = curl_exec($curl);

	curl_close($curl);

	$resp_arr = json_decode($response,true);
	// print_r($resp_arr);die;
	$allowedImageTypes = array(
	    'image/jpeg',
	    'image/png',
	    // Add more as needed
	);

	if($resp_arr['status'] == 200 && $resp_arr['success'] == true){
		if($resp_arr['data']['image'] && in_array($resp_arr['data']['image']['mime'], $allowedImageTypes)){
			$data_from_open_ai = get_analytics($resp_arr['data']['url']);
			if($data_from_open_ai['status']){
				$data_from_open_ai['success'] = true;
				$data_from_open_ai['webshot_url'] = $resp_arr['data']['url'];
				$final_response = $data_from_open_ai;
			}else{
				
				$err_msg = ($data_from_open_ai['error'] && $data_from_open_ai['error']['message']) ? $data_from_open_ai['error']['message'] : "Couldn't analyse the uploaded file";
				// print_r($data_from_open_ai['error']);die;
				$final_response = array('success'=>false,'msg'=>$err_msg);
			}
		}else{
			$final_response = array('success'=>false,'msg'=>"Please upload a valid screenshot");
		}
	}else{
		$final_response = array('success'=>false,'msg'=>"Couldn't Upload the file");
	}
	echo json_encode($final_response);
} else {
	$final_response = array('success'=>false,'msg'=>'File upload error: ' . $_FILES['webshot_file']['error']);
	echo json_encode($final_response);
}

// Fuunction to get the insight of the screen shot from CHAT GPT
function get_analytics($url){
    $curl = curl_init();

    // Getting analysis from the chatgpt
    $curl_post_fields = [
	    "model" => "gpt-4-vision-preview",
	    "messages" => [
	        [
	            "role" => "user",
	            "content" => [
	                [
	                    "type" => "text",
	                    "text" => "Could you analyse this screen shot of the webpage for the CRO optimization and give the score of the website and positives of the website and detailed list of things that need CRO Checklist improvement for more lead conversion, i need that response in below sample JSON format 
	 {score: 90,positives:[{heading : \'positive heading\',info:\'Info about the positiveness\'}],improvements:[{heading : \'improvement heading\',info:\'Info about the improvement\'}]} if the given image is not an webpage then give the score as 0",
	                ],
	                [
	                    "type" => "image_url",
	                    "image_url" => [
	                        "url" =>
	                            $url,
	                    ],
	                ],
	            ],
	        ],
	    ],
	    "max_tokens" => 1000,
	];
	$curl_post_fields = json_encode($curl_post_fields);
    curl_setopt_array($curl, array(
      CURLOPT_URL => 'https://api.openai.com/v1/chat/completions',
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => '',
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 120,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => 'POST',
      CURLOPT_POSTFIELDS => $curl_post_fields,
      CURLOPT_HTTPHEADER => array(
        'Content-Type: application/json',
        'Authorization: Bearer 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'
      ),
    ));

    // $response = curl_exec($curl);
    // // echo $response;
    // curl_close($curl);
    $response_arr = json_decode($response,true);
    $transformed = return_sample_response();
    return array("status" => true,"data" => $transformed);
    
    //check whether the request was given proper message
    if(!empty($response_arr['choices'][0]['message']['content'])){
        $message = $response_arr['choices'][0]['message']['content'];

        // Extract the JSON from the message content
        $transformed = transform_response($message);
        // $transformed = json_decode($message);
        // echo $transformed;die;
        return array("status" => true,"data" => $transformed);
    }else if($response_arr['error'] && $response_arr['error']['message']){
    	return array("status" => false,'error' => array("message"=>$response_arr['error']['message']));
    }
    else{
        // CHat GPT doesn't provided proper details
        return array("status" => false);
    }
}

function transform_response($message){
    $json = extract_json($message);
    // echo $json;
    return ($json) ? $json : null;
}

function return_sample_response(){
    $data = array(
    'score' => 87,
    'positives' => array(
                        array(
                            'heading' => 'Strategic Call-to-Action Placement:',
                            'info' => "The website effectively guides users with clear 'Start a free trial' and 'Book a demo' buttons."
                        ),
                        array(
                            'heading' => 'Compelling Value Proposition:',
                            'info' => "The headline 'Make AI your expert customer support agent' succinctly communicates the platform's unique value."
                        ),
                        array(
                            'heading' => 'Appealing Feature Showcase:',
                            'info' => "Engaging features like 'Personalized onboarding help' and '95+ languages supported' are prominently listed."
                        ),
                        array(
                            'heading' => 'Trust-Boosting Recognition:',
                            'info' => "The inclusion of 'PRODUCT HUNT #1 Product of the Day' badge serves as a powerful trust indicator."
                        )
                    ),
                    'improvements' => array(
                        array(
                            'heading' => 'Streamlined Chatbot Presentation:',
                            'info' => "Consider minimizing the chatbot overlay by default to enhance user experience."
                        ),
                        array(
                            'heading' => 'Enhanced Readability Through Contrast:',
                            'info' => "Improve color contrast between text and background for better readability, prioritizing accessibility."
                        ),
                        array(
                            'heading' => 'Detailed Feature Descriptions:',
                            'info' => "Provide more comprehensive descriptions of features to clearly communicate unique advantages."
                        ),
                        array(
                            'heading' => 'Social Proof Integration:',
                            'info' => "Introduce customer testimonials or case studies directly on the homepage for added social proof."
                        )
                    )
                );
    return $data;    
}
function extract_json($str) {
    // print_r($str);
    $regex = '/{(?:[^{}]|(?R))*}/'; // recursive pattern to match braces
    if (preg_match($regex, $str, $matches)) {
        $json = $matches[0];
        // echo $json;die;
        $json = str_replace(["\r", "\n"], '', $json); // remove newlines
        $json = json_decode($json,true);
        $json = is_array($json) ? $json : array();
        return $json;
    } else {
        return array();
    }
}



?>
