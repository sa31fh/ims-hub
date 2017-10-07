<?php
session_start();
require_once "database/category_table.php";
require_once "mpdf/vendor/autoload.php";

if (!isset($_SESSION["username"])) {
    header("Location: login.php");
    exit();
}
if (isset($_POST["table_data"])) {
    $mpdf = new mPDF("", "A4", 0, 'roboto', 0, 0, 0, 0, 0, 0);
    $stylesheet = file_get_contents("css/pdf_styles.css");
    $mpdf->useSubstitutions=false;
    $mpdf->simpleTables = true;
    $mpdf->WriteHtml($stylesheet, 1);
    $mpdf->WriteHtml($_POST["table_data"], 2);
    $mpdf->Output($_POST["table_name"]." - ".$_POST["table_date"].".pdf", "D");
}
if (isset($_SESSION["last_activity"]) && $_SESSION["last_activity"] + $_SESSION["time_out"] * 60 < time()) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}
$_SESSION["last_activity"] = time();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Print Preview</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="font_roboto">
    <div class="toolbar_print">
        <div class="toolbar_div">
            <a href="category_status.php" class="option" id="back">back</a>
        </div>
        <div class="toolbar_div">
            <label class="switch" id="toolbar_toggle">
                <input class="switch-input" type="checkbox" onclick=checkRequired() />
                <span class="switch-label" data-on="Required" data-off="All"></span>
                <span class="switch-handle"></span>
            </label>
        </div>
        <div class="toolbar_div">
            <a id="print_share" class="option" onclick=sendPrint()>Share</a>
        </div>
        <div class="toolbar_div">
            <a id="print_pdf" class="option" onclick=printPdf()>PDF</a>
        </div>
        <div class="toolbar_div">
            <a id="print_all" class="fa-print option" onclick=printAll()>Print All</a>
        </div>
    </div>
    <div class="main overflow_hidden">
            <div class="div_category">
                <h4 id="print_suppliers">Suppliers</h4>
                <ul class="category_list home_category_list font_roboto" >
                <?php $result = CategoryTable::get_categories($_SESSION["date"]);
                     while ($row = $result->fetch_assoc()): ?>
                     <li class="list_category_li">
                        <span id="category_name"><?php echo $row["name"]; ?></span>
                        <input type="hidden" id="category_id" name="category_id" value="<?php echo $row['id'] ?>">
                     </li>
                <?php endwhile ?>
            </ul>
            </div>

            <div id="div_print_table">
                <table class="table_view" id="print">
                    <tr id="print_date" class="row">
                        <th colspan="7">
                            <div id="table_date_heading"></div>
                            <span id="table_date_span"><?php echo date_format((date_add(date_create($_SESSION["date"]), date_interval_create_from_date_string("1 day"))), 'D, jS M Y'); ?></span>
                            <div class="print_table_date"><?php echo "created on ".date('jS M Y', strtotime($_SESSION["date"])); ?></div>
                        </th>
                    </tr>
                    <tr class="heading">
                        <th>Item</th>
                        <th>Unit</th>
                        <th>Quantity</th>
                        <th>Notes</th>
                    </tr>
                    <tbody class="font_roboto" id="item_tbody"></tbody>
                </table>
            </div>
    </div>

    <div class="div_popup_back">
        <div class="div_popup popup_share">
            <div class="popup_titlebar">
                <span>New Message</span>
                <span class="popup_close" id="popup_close"></span>
            </div>
            <iframe id="popup_frame" name="popup_frame" src="" frameborder="0"></iframe>
        </div>
    </div>

    <input type="hidden" id="session_date" value="<?php echo $_SESSION["date"] ?>">
    <input type="hidden" id="formatted_date" value="<?php echo date_format((date_add(date_create($_SESSION["date"]), date_interval_create_from_date_string("1 day"))), 'D, jS M Y'); ?>">

    <form action="compose_messages.php" method="post" id="print_form" target="popup_frame">
        <input type="hidden" id="print_table_date" name="print_table_date">
        <input type="hidden" id="print_table_name" name="print_table_name">
        <input type="hidden" id="new_print_data" name="new_print_data">
    </form>

    <form action="print_preview.php" method="post" id="test_form" name="test_form">
        <input type="hidden" id="table_data" name="table_data">
        <input type="hidden" id="table_date" name="table_date">
        <input type="hidden" id="table_name" name="table_name">
    </form>
</body>
</html>

<script type="text/javascript" src="//code.jquery.com/jquery-2.2.0.min.js"></script>
<?php if ($_SESSION["date"] <= date('Y-m-d', strtotime("-".$_SESSION["history_limit"]))): ?>
    <script> $("input").prop("readonly", true); </script>
<?php endif ?>
<script>

    function getInventory(categoryId , callBack) {
        var date = document.getElementById("session_date").value;
        if ($("#item_tbody").html() == "") {
            $.post("jq_ajax.php", {getPrintPreview: "", categoryId: categoryId, date: date}, function(data, status) {
                document.getElementById("item_tbody").innerHTML = data;
                $("#item_tbody").children().hide();
                $("#item_tbody").children().each(function() {
                    if ($(this).find("#cat_id").val() == categoryId) {
                        $(this).show();
                    }
                });
            });
        } else {
            $("#item_tbody").children().hide();
            $("#item_tbody").children().each(function() {
                if ($(this).find("#cat_id").val() == categoryId) {
                    $(this).show();
                }
            });
        }
        typeof callBack === "function" ? callBack() : "";
        if ($("#toolbar_toggle .switch-input").prop("checked")) { checkRequired(); }
    }

    function updateNotes(obj) {
        var itemNote = obj.value;
        var itemId = obj.parentNode.parentNode.children[7].value;
        var itemQuantity = obj.parentNode.parentNode.children[3].innerHTML;
        itemQuantity = (itemQuantity == "-") ? "NULL" : itemQuantity;
        var itemDate = document.getElementById("session_date").value;

        $.post("jq_ajax.php", {itemId: itemId, itemDate: itemDate, itemQuantity: itemQuantity, itemNote: itemNote});
    }

    function printPdf() {
        createTable(function(table) {
            $("#table_data").val(table.outerHTML);
            document.getElementById("table_name").value = $(".list_category_li.active").find("#category_name").html();
            document.getElementById("table_date").value = $("#print_date").children().find("#table_date_span").html();
            $("#test_form").submit();
        });
    }

    function printAll() {
        var date = document.getElementById("session_date").value;
        var expectedSales = $(".print_expected").val();
        var required = $("#toolbar_toggle .switch-input").prop("checked") ? "true" : "false";
        $.post("jq_ajax.php", {printAll: "", date: date, expectedSales: expectedSales, required: required}, function(data, status) {
            $("#table_data").val(data);
            document.getElementById("table_name").value = "Print All";
            document.getElementById("table_date").value = document.getElementById("session_date").value;
            $("#test_form").submit();
        });
    }

    function sendPrint() {
        createTable(function(table) {
            document.getElementById("new_print_data").value = table.outerHTML;
            document.getElementById("print_table_name").value = $(".list_category_li.active").find("#category_name").html();
            document.getElementById("print_table_date").value = $("#print_date").children().find("#table_date_span").html();
            $(".div_popup_back").css("display", "block");
            $("#print_form").submit();
        });
    }

    function createTable(callBack) {
        var table = document.createElement("table");
        var row_count = 0;
        table.setAttribute("class", "table_view");
        table.innerHTML += "<tr><th colspan='7' class='table_title'> " +
                            $(".list_category_li.active").find("#category_name").html(); + "</th></tr>";
        $(".table_view tr").each(function() {
            if($(this).css('display') != 'none') {
                var row = $(this).clone()[0];
                var cell = "";
                $(this).children().each(function() {
                    if ($(this).attr("type") == "hidden") {
                        return true;
                    } else {
                        cell += this.outerHTML;
                    }
                });
                row.innerHTML = cell;
                table.innerHTML += row.outerHTML;
            }
        });
        callBack(table);
    }

    function checkRequired() {
        if ($("#toolbar_toggle .switch-input").prop("checked")) {
            $(".td_quantity").each(function() {
              if ((this.innerHTML <=0 || this.innerHTML == "-") && $(this).nextAll("#td_notes").html() == "") {
                $(this).parent().hide();
              }
            });
        } else {
            getInventory($(".list_category_li.active").find("#category_id").val());
        }
    }

    $(document).ready(function() {

        $(".list_category_li:first").each(function() {
            getInventory($(this).find("#category_id").val());
            $(this).addClass("active");
        });

        $(".list_category_li").click(function() {
            getInventory($(this).find("#category_id").val());
            $(".list_category_li").removeClass("active");
            $(this).addClass("active");
        });

        $("#popup_close").click(function() {
            $(".div_popup_back").fadeOut(190, "linear");
            $(".main_iframe").removeClass("blur");
        });

    });
</script>