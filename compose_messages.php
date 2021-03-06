<?php
session_start();
require_once "database/user_table.php";
require_once "database/conversation_table.php";
require_once "database/user_group_table.php";
require_once "database/user_group_list_table.php";
require_once "database/contacts_table.php";
require_once "database/notification_status_table.php";
require_once "mpdf/vendor/autoload.php";
include_once 'phpmailer/PHPMailerAutoload.php';


if (!isset($_SESSION["username"])) {
    header("Location: login.php");
    exit();
}
if (isset($_SESSION["last_activity"]) && $_SESSION["last_activity"] + $_SESSION["time_out"] * 60 < time()) {
    session_unset();
    session_destroy();
?>
    <script>
        window.parent.location.href = window.parent.location.href;
    </script>
<?php
exit();
}
$_SESSION["last_activity"] = time();
$contact_data = ContactsTable::get_contacts();

if (isset($_POST["message"])) {
    $mail = new PHPMailer;
    $mail->setFrom('do-not-reply@auntyskitchen.ca', 'Auntys Kitchen - Kitchener');
    $mail->Body = "Title: ".$_POST["title"]."\n \n".$_POST["message"];
    $mail->Subject = $_SESSION['first_name']." ".$_SESSION["last_name"]. " sent you a message: ".$_POST["title"];
    $mail->isSMTP();
    $mail->Port = 25;
    $mail->SMTPAuth = false;
    $mail->SMTPSecure = false;

    if (isset($_POST["attachment"])) {
        $mpdf = new mPDF("", "A4", 0, 'roboto', 0, 0, 0, 0, 0, 0);
        $stylesheet = file_get_contents("css/pdf_styles.css");
        $mpdf->useSubstitutions=false;
        $mpdf->simpleTables = true;
        $mpdf->WriteHtml($stylesheet, 1);
        $mpdf->WriteHtml($_POST["attachment"], 2);
        $content = $mpdf->Output('', 'S');
        $mail->addStringAttachment($content, $_POST["attachment_title"].".pdf");
    }
    $result = NotificationStatusTable::get_alert_info("notify by email", "received messages");
    $recipient_address = 0;
    $contact_address = 0;
    foreach ($_POST["recipient"] as $recipient) {
        ConversationTable::create_conversation($_SESSION["username"], $recipient, $_POST["title"],
            $_POST["message"], gmdate("Y-m-d H:i:s"),
            isset($_POST["attachment"]) ? $_POST["attachment"] : null,
            isset($_POST["attachment_title"]) ? $_POST["attachment_title"] : null, "read", "unread");

        while ($row = $result->fetch_assoc()) {
            if ($row["noti_status"] == 1 AND $row["sub_noti_status"] == 1 AND $row["user_name"] == $recipient) {
                if ($recipient_address == 0) {
                    $mail->AddAddress($row["email"], $row["first_name"].' '.$row["last_name"]);
                    $recipient_address = 1;
                } else {
                    $mail->AddCC($row["email"], $row["first_name"].' '.$row["last_name"]);
                }
            }
        }
        mysqli_data_seek($result, 0);
    }
    foreach ($_POST["contacts"] as $contact) {
        while ($row = $contact_data->fetch_assoc()) {
            if ($row["name"] == $contact) {
                if ($contact_address == 0) {
                    $mail->AddAddress($row["email"], $row["name"]);
                    $contact_address = 1;
                } else {
                    $mail->AddCC($row["email"], $row["name"]);
                }
            }
        }
        mysqli_data_seek($contact_data, 0);
    }

    if(!$mail->send()) {
      echo 'Message was not sent.';
      echo 'Mailer error: ' . $mail->ErrorInfo;
    } else {
      echo 'Message has been sent.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Compose</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="main_iframe compose_frame font_open_sans">
        <form id="compose_form" class="compose_form" onsubmit=submitMessage() action="compose_messages.php" method="post">
            <div class="div_fade"></div>
            <div class="compose_recipient">
                <span id="send_label">Send To</span>
                <div id="container"></div>
                <div class="name_drawer">
                    <div class="toolbar_print">
                        <div class="toolbar_div option selected">users</div>
                        <div class="toolbar_div option">contacts</div>
                        <div class="toolbar_div option">groups</div>
                    </div>
                    <ul id="user_list">
                    <?php $result = UserTable::get_users(); ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                            <li class="contact_li" data-user="<?php echo $row['username'] ?>">
                                <div id="username">
                                    <div>
                                        <span class="entypo-user avatar"></span>
                                    </div>
                                    <div>
                                        <span id="name"><?php echo $row["first_name"]." ".$row["last_name"]?></span>
                                        <span id="user_name"><?php echo $row["username"]?></span>
                                    </div>
                                </div>
                            </li>
                    <?php endwhile ?>
                    </ul>
                    <ul class="display_none" id="contact_list">
                        <?php while ($row = $contact_data -> fetch_assoc()): ?>
                            <li class="contact_li" data-user="<?php echo $row['name'] ?>">
                                <div id="username">
                                    <div>
                                        <span class="entypo-user avatar"></span>
                                    </div>
                                    <div>
                                        <span id="name"><?php echo $row["name"]?></span>
                                        <span id="user_name"><?php echo $row["email"]?></span>
                                    </div>
                                </div>
                            </li>
                        <?php endwhile ?>
                        <?php mysqli_data_seek($contact_data, 0) ?>
                    </ul>
                    <ul class="display_none" id="group_list">
                        <?php $result = UserGroupTable::get_groups() ?>
                        <?php while ($row = $result -> fetch_assoc()): ?>
                            <li class="group_li">
                                <span id="name"><?php echo $row["name"] ?></span>
                                <?php $users = mysqli_fetch_all(UserGroupListTable::get_users($row["id"]), MYSQLI_ASSOC); ?>
                                <input type="hidden" id="group_users" value=<?php echo $users = json_encode($users) ?>>
                            </li>
                        <?php endwhile ?>
                    </ul>
                </div>
            </div>
            <div class="compose_title">
                <input type="text" name="title" placeholder="Title">
            </div>
            <div class="compose_text">
                <textarea name="message" placeholder="Message" required></textarea>
            </div>
            <div class="compose_attachment">
                <img src="images/paperclip.png" alt="" width="24px" height="21px">
            <?php if (isset($_POST["new_print_data"])): ?>
                <input id="name" name="attachment_title" value="<?php echo $_POST["print_table_name"]. ' - ' . $_POST["print_table_date"]?>"></input>
                <input type="hidden" name="attachment" id="attachment" value='<?php  echo $_POST["new_print_data"] ?>'>
            <?php endif ?>
            </div>
            <div class="compose_toolbar">
                <input type="submit" class="button"  value="Send">
            </div>
        </form>
    </div>
</body>
</html>

<script type="text/javascript" src="//code.jquery.com/jquery-2.2.0.min.js"></script>
<script>
    function submitMessage() {
        if (window.parent.document.title == "Messages") {
            window.parent.location.href = window.parent.location.href;
        } else {
            $(".div_popup_back", window.parent.document).css("display", "none");
        }
    }

    $(document).ready(function() {
        $(".compose_recipient").click(function() {
            $(".name_drawer").css("display", "flex");
            $(".div_fade").css("display", "block");
        });

        $(".option").click(function() {
            $(".option").removeClass("selected");
            $(this).addClass("selected");
            switch ($(this).html()) {
                case 'groups':
                    $("#contact_list").css("display", "none");
                    $("#user_list").css("display", "none");
                    $("#group_list").css("display", "inline-block");
                    break;
                case "users":
                    $("#group_list").css("display", "none");
                    $("#contact_list").css("display", "none");
                    $("#user_list").css("display", "inline-block");
                    break;
                case "contacts":
                    $("#group_list").css("display", "none");
                    $("#user_list").css("display", "none");
                    $("#contact_list").css("display", "inline-block");
                    break;
            }
        });

        $("#user_list .contact_li").click(function() {
            var contact = $(this).html();
            $(this).toggleClass(function() {
                if ($(this).hasClass("selected")) {
                    $(".name_tag").each(function() {
                        if ($(this).children("span").html() == contact) {
                            $(this).remove();
                        }
                    });
                } else {
                    var span = "<div class='name_tag'><span>"+contact+"</span>"+
                                   "<input type='hidden' name='recipient[]' value='"+$(this).attr("data-user")+"'>"+
                               "</div>"
                    $("#container").append(span);
                }
                return "selected";
            });
        });

        $("#contact_list .contact_li").click(function() {
            var contact = $(this).html();
            $(this).toggleClass(function() {
                if ($(this).hasClass("selected")) {
                    $(".name_tag").each(function() {
                        if ($(this).children("span").html() == contact) {
                            $(this).remove();
                        }
                    });
                } else {
                    var span = "<div class='name_tag'><span>"+contact+"</span>"+
                                   "<input type='hidden' name='contacts[]' value='"+$(this).attr("data-user")+"'>"+
                               "</div>"
                    $("#container").append(span);
                }
                return "selected";
            });
        });

        $(".group_li").click(function() {
            $(".name_tag").remove();
            $("#user_list .contact_li").removeClass("selected");

            if ($(this).hasClass("selected")) {
                $(this).removeClass("selected");
            } else {
                $(".group_li").removeClass("selected");
                $(this).addClass("selected");
                var users = JSON.parse($(this).find("#group_users").val());
                for (var i = 0; i < users.length; i++) {
                    $("#user_list .contact_li").each(function() {
                        if ($(this).attr("data-user") == users[i].username) {
                            if (!$(this).hasClass("selected")) {
                                $(this).addClass("selected");
                                var span = "<div class='name_tag'><span>"+$(this).html()+"</span>"+
                                               "<input type='hidden' name='recipient[]' value='"+users[i].username+"'>"+
                                           "</div>"
                                $("#container").append(span);
                            }
                        }
                    });

                }
            }
            return "selected";
        });

        $(".div_fade").click(function() {
            $(".div_fade").css("display", "none");
            $(".name_drawer").css("display", "none");
        });
    });
</script>
