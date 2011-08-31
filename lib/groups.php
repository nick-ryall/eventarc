<?php

	require_once('class.eventarc.php');

	if(isset($_GET["apikey"]) && isset($_GET["uname"])) {
		
		$eventarc = new Eventarc($_GET["apikey"], $_GET["uname"]);
		$groups = $eventarc->group_list();
		
		header("Content-type: application/json");
		echo json_encode($groups);
		
	}