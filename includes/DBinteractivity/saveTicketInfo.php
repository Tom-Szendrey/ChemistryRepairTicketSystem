<?php
/*
Created By: Tom Szendrey
March 2019
This will be called by showTickets.js and be used to save the information in a displayed table (on showTickets.html)
to the database.
*/

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
$topLayer = str_replace($_SERVER['DOCUMENT_ROOT'], "", $_SERVER['SCRIPT_FILENAME']);
$path = $_SERVER['DOCUMENT_ROOT'] . "/" . explode("/", $topLayer)[1];

require $path . '/includes/libs/PHPMailer/src/Exception.php';
require $path . '/includes/libs/PHPMailer/src/PHPMailer.php';
require $path . '/includes/libs/PHPMailer/src/SMTP.php';
// include_once '../config.php';
// include_once '../connect.php';
// include_once '../authenticate.php';
$mail = new PHPMailer();


/* for orginizational purposes. */
function main($mail){
    // include_once '../config.php';
    include_once '../connect.php';
    include_once '../authenticate.php';
    if(!$auth){
        // User is not authenticated
        //Send an error message that was created in authenticate.php
        echo $jsonMsg;
        exit();
    }

    date_default_timezone_set('America/Toronto');

    //parse the POST data.
    parse_str($_POST['data'],$output);

    //Updates Ticket table with new information.
    for ($i = 1; $i < $output['tableHeight']; $i++){
        //get the row's ticketID, the oldStatus (before change/in database) and newStatus (what is on the current page.)
        $ticketID = $output['value0a' . $i]; //value0aX = ID
        $newStatus = $output['value3a' . $i]; //value3aX = status
        if ($output['value10a' . $i] != ''){ //value10aX = tech
            $techRef = getRefNumberFromUsername($conn,$output['value10a' . $i]);
        }
        else{
            $techRef = '';
        }
        $sqlSelect = "SELECT `status` from `tickets` where ticket_id = $ticketID;";
        $sqlPrep = $conn->query($sqlSelect);
        if(!$sqlPrep->execute()) {
            echo('Error: The command could not be executed, and the information could not be read.');
            exit();
        }
        $oldStatusFetch = $sqlPrep->fetch(PDO::FETCH_NUM);
        $oldStatus = $oldStatusFetch[0];
        //If the user has just closed a ticket, update the closed_time aswell as the status.
        // echo "----------- Row: $i Ticket: $ticketID assigned_tech: $techRef ---------";
        if($oldStatus != 'Closed' && $newStatus == 'Closed'){
            $sqlUpdate = "UPDATE `tickets` set `status` = '" . $newStatus . "', `closed_time` = '" . date("Y-m-d h:i:s") . "', assigned_tech = '$techRef' where `ticket_id` = " . $ticketID . ";";
         }
        //Otherwise only update the status.
        else{
            $sqlUpdate = "UPDATE `tickets` set `status` = '" . $newStatus . "', assigned_tech = '$techRef' where `ticket_id` = " . $ticketID . ";";
        }
        try{
            $sqlResult = $conn->query($sqlUpdate);
            if ($oldStatus != $newStatus){
                $r = getUserEmail($conn,$ticketID); 
                $s = "Status change of Ticket #" . $ticketID; 
                $b = "This email is to inform you that your ticket # " . $ticketID . " has had the status updated from: " . $oldStatus . " To: " . $newStatus; 
                sendEmail("StefanUrosgmail.ca",$s,$b,$mail);
                updateMessage($conn,$ticketID,$uid,$newStatus);
            }//end old != new
            // echo "  Updated     ";
        }catch(PDOException $e){
            echo $e;
        }
    }//end i,tableHeight
}//end main

/* Helper function, used to turn a username into a reference number. This will help organize when updating assigned_tech
inputs: $username: the username given, this will be the username in the table profile.
outputs: $refNumber: reference number. This will be the unique_id in profile, or the requested_by/assigned_tech for tickets.
*/
function getRefNumberFromUsername($conn,$username){
    $sql = "SELECT DISTINCT unique_id FROM `profile` WHERE `username` = '" . $username . "';";
    $sqlPrepared = $conn->prepare($sql);
    if(!$sqlPrepared->execute()){
        echo "The given username: $username 's unique id could not be found.";
        $refNumber = '';
    }
    $row = $sqlPrepared->fetch(PDO::FETCH_NUM);
    $refNumber = $row[0];
    return $refNumber;
}

function getUserEmail($conn, $ticketID){
    $sql = "SELECT `profile`.email 
        from (`tickets`
        LEFT JOIN `profile` ON tickets.requested_by = `profile`.unique_id)
        where tickets.ticket_id = " . $ticketID . ";";
    $sqlPrep = $conn->query($sql);
    $sqlPrep->execute();
    $getEmail = $sqlPrep->fetch(PDO::FETCH_NUM);
    $email = $getEmail[0];
    return $email;
}

function updateMessage($conn, $ticketID,$uid,$status){
    $insertMessage = "INSERT INTO messages_list VALUES ('$ticketID', NULL, '$uid', NULL, '$status', 1);";
    $stmt = $conn->prepare($insertMessage);
    $stmt->execute();
}

function sendEmail($r,$s,$b,$mail){   
    // $topLayer = str_replace($_SERVER['DOCUMENT_ROOT'], "", $_SERVER['SCRIPT_FILENAME']);
    // $path = $_SERVER['DOCUMENT_ROOT'] . "/" . explode("/", $topLayer)[1];

    // require $path . '/includes/libs/PHPMailer/src/Exception.php';
    // require $path . '/includes/libs/PHPMailer/src/PHPMailer.php';
    // require $path . '/includes/libs/PHPMailer/src/SMTP.php';

    // $mail = new PHPMailer();

    if(	isset($r) &&
        isset($s) &&
        isset($b))
    {
        try
        {
            $mail->isSMTP();
            $mail->Host = "smtp.gmail.com";
            $mail->SMTPAuth = true;
            $mail->Username = $eUser . "@gmail.com"; //looks fishy
            $mail->Password = $ePass;
            $mail->SMTPSecure = "ssl";
            $mail->Port = "465";
            
            $recipient = $r;
            $subject = $s;
            $body = $b;
            
            // Recipents
            $mail->SetFrom('no-reply@QueensChemistryRepair.com');
            $mail->AddAddress($recipient);
            
            // Content
            $mail->isHTML();
            $mail->Subject = $subject;
            $mail->Body = $body;
            
            $mail->Send();

            echo "200 OK";
        }
        catch(Exception $e)
        {
            echo "Error: " . $e;
        }
    }
    else
    {
        echo "Error: " . $e;
    }

}
main($mail);
?>