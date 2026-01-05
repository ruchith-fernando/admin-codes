<?php
session_start();
include "connections/connection.php";

if (!isset($_SESSION['user_level']) || !in_array($_SESSION['user_level'], ['manager', 'super-admin'])) {
    die("Unauthorized");
}

$file = isset($_GET['file']) ? $_GET['file'] : '';
$docNumber = isset($_GET['doc']) ? $_GET['doc'] : '';

if (!$file || !$docNumber) {
    die("Invalid document.");
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Secure Print Viewer</title>
    <style>
        body, html {
            margin: 0;
            padding: 0;
            overflow: hidden;
        }
        iframe {
            width: 100vw;
            height: 100vh;
            border: none;
        }
    </style>
    <script>
        function promptAndPrint() {
            let hris = prompt("Enter the HRIS of the person requesting this document:");
            if (!hris || hris.trim() === "") {
                alert("HRIS is required.");
                return;
            }

            let copies = prompt("Enter number of copies:");
            if (!copies || isNaN(copies) || parseInt(copies) < 1) {
                alert("Please enter a valid number of copies.");
                return;
            }

            // Send log to server
            fetch("log-print.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: "docNumber=<?php echo urlencode($docNumber); ?>&copies=" + encodeURIComponent(copies) + "&hris=" + encodeURIComponent(hris)
            }).then(() => {
                // Print after logging
                const frame = document.getElementById('pdfFrame');
                if (frame) {
                    frame.contentWindow.focus();
                    frame.contentWindow.print();
                }
            });
        }

        // Disable PrintScreen, Right Click
        document.addEventListener("keydown", function (e) {
            if (e.key === "PrintScreen" || e.ctrlKey || e.metaKey) {
                e.preventDefault();
                alert("This action is blocked.");
            }
        });

        window.oncontextmenu = function () {
            return false;
        };

        window.onload = function () {
            setTimeout(promptAndPrint, 500);
        };
    </script>
</head>
<body>
    <iframe id="pdfFrame" src="<?php echo htmlspecialchars($file); ?>#toolbar=0&navpanes=0&scrollbar=0" allow="fullscreen"></iframe>
</body>
</html>
