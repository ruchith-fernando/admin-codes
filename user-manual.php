<!-- user-manual.php -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Mobile Details - User Guide</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="styles.css">
    <style>
        .toc a {
            display: block;
            padding: 4px 0;
            color: #0d6efd;
            text-decoration: none;
        }
        .toc a:hover {
            text-decoration: underline;
        }
        .back-to-top {
            position: fixed;
            bottom: 20px;
            right: 20px;
            display: none;
            background: #0d6efd;
            color: white;
            padding: 10px 15px;
            border-radius: 5px;
            text-decoration: none;
            font-size: 14px;
        }
        .back-to-top:hover {
            background: #0b5ed7;
        }
        .section h2 {
            padding-top: 60px;
            margin-top: -60px;
        }
    </style>
</head>
<body class="bg-light">
<button class="menu-toggle" onclick="toggleMenu()">&#9776;</button>
<div class="sidebar" id="sidebar">
    <?php include 'side-menu.php'; ?>
</div>
<div class="content font-size" id="contentArea">
    <div class="container">
        <div class="card shadow bg-white rounded p-4">
            <h2 class="mb-4">User Guide</h2>
            <div class="toc mb-3">
            <div class="toc mb-3">
            <h5>Table of Contents</h5>
            <div class="row">
                <div class="col-md-4 mb-2">
                    <a href="#section1">1. View Employee Mobile Connection Details</a>
                </div>
                <div class="col-md-4 mb-2">
                    <a href="#section2">2. Upload Mobile Connection Bill CSV File</a>
                </div>
                <div class="col-md-4 mb-2">
                    <a href="#section3">3. Mobile Connection Bill Report - HR</a>
                </div>
                <div class="col-md-4 mb-2">
                    <a href="#section4">4. Mobile Connection Bill Report – Finance</a>
                </div>
                <div class="col-md-4 mb-2">
                    <a href="#section5">5. Mobile Connection Bill Report – Police</a>
                </div>
                <div class="col-md-4 mb-2">
                    <a href="#section6">6. Resignation List Report</a>
                </div>
                <div class="col-md-4 mb-2">
                    <a href="#section7">7. Disconnected Mobile Numbers Report</a>
                </div>
                <div class="col-md-4 mb-2">
                    <a href="#section8">8. Upload Employee Master CSV</a>
                </div>
                <div class="col-md-4 mb-2">
                    <a href="#section9">9. Upload Inactive Employee CSV</a>
                </div>
                <div class="col-md-4 mb-2">
                    <a href="#section10">10. Upload Resignation List CSV</a>
                </div>
                <div class="col-md-4 mb-2">
                    <a href="#section11">11. Submit SIM / Mobile / Transfer Request</a>
                </div>
                <div class="col-md-4 mb-2">
                    <a href="#section12">12. Recommend SIM / Mobile / Transfer Requests</a>
                </div>


                
            </div>
        </div>
        <hr class="my-5">
    </div>
            <div class="section" id="section1">
                <h2>1. View Employee Mobile Details</h2>
                <p>This page helps users view and manage a list of employees who have been issued mobile connections. You can search for employees, view their information, and navigate through the list easily.</p>
                <h5>Steps:</h5>
                <ol>
                    <li>Go to the <strong>Employee List</strong> page using the menu.</li>
                    <li>Use the search box labeled <em>"Search HRIS, Full Name, NIC No, Mobile Number"</em>.</li>
                    <li>Enter HRIS number, name, NIC, or mobile number to filter results.</li>
                    <li>Click any employee row to view more details in a pop-up box.</li>
                    <li>Close the pop-up by clicking <strong>X</strong> or <strong>Close</strong>.</li>
                </ol>
                <h5>Mandatory Information:</h5>
                <ul>
                    <li>Full Name</li>
                    <li>NIC Number</li>
                    <li>Mobile Number</li>
                    <li>HRIS Number</li>
                </ul>
                
                <h5>Tips:</h5>
                <ul>
                    <li>Partial searches work (e.g., first few letters).</li>
                    <li>Check for typos if no results appear.</li>
                    <li>Refresh the page if it becomes unresponsive.</li>
                </ul>
                <hr class="my-5">
            </div>

            <div class="section" id="section2">
                <h2>2. Upload Mobile Bill - PDF File Format</h2>
                <p>This feature allows you to upload a Dialog mobile bill in PDF format. The system extracts relevant billing data from the PDF and saves it into the database automatically.</p>

                <h5>Steps:</h5>
                <ol>
                    <li>Navigate to the <strong>Upload Dialog Bill PDF</strong> page from the menu.</li>
                    <li>Click <strong>Select PDF File</strong> and choose your file.</li>
                    <li>Click the <strong>Upload</strong> button.</li>
                    <li>The system will read the billing month and data from the PDF.</li>
                    <li>If the data is valid and not already uploaded, it will be saved and a success message will appear.</li>
                    <li>If there is an error (e.g., duplicate billing month or parsing issue), an error message will be displayed.</li>
                </ol>

                <h5>Important Notes:</h5>
                <ul>
                    <li>Only Dialog mobile bill PDFs are supported at this time.</li>
                    <li>The system skips summary pages, headers, totals, and non-billable lines automatically.</li>
                    <li>Each row must contain all required columns (16 fields).</li>
                    <li>Duplicate records for the same month and mobile number are ignored.</li>
                    <li>The system extracts the billing month from the "BILL PERIOD" line in the PDF.</li>
                </ul>

                <h5>Supported Data Columns (automatically extracted):</h5>
                <div class="row">
                    <div class="col-md-4">
                        <ul>
                            <li>Mobile Number</li>
                            <li>Previous Due Amount</li>
                            <li>Payments</li>
                            <li>Total Usage Charges</li>
                            <li>IDD</li>
                            <li>Roaming</li>
                        </ul>
                    </div>
                    <div class="col-md-4">
                        <ul>
                            <li>VAS</li>
                            <li>Discounts</li>
                            <li>Balance Adjustments</li>
                            <li>Commitment Charges</li>
                            <li>Late Payment Charges</li>
                            <li>Gov Tax</li>
                        </ul>
                    </div>
                    <div class="col-md-4">
                        <ul>
                            <li>VAT</li>
                            <li>Add to Bill</li>
                            <li>Charges</li>
                            <li>Total Amount Payable</li>
                            <li>Billing Month (auto-detected)</li>
                        </ul>
                    </div>
                    <hr class="my-5">
                </div>
            </div>


            <div class="section" id="section3">
                <h2>3. Mobile Bill Report - HR</h2>
                <p>This page allows you to view and filter mobile bill records of employees. You can search using mobile number, employee name, HRIS, NIC, or billing month. You can also download the results in Excel format.</p>

                <h5>Steps:</h5>
                <ol>
                    <li>Navigate to the <strong>Mobile Bill Report - HR</strong> page.</li>
                    <li>Use the search box to filter records by typing in mobile number, name, NIC, HRIS, or billing month.</li>
                    <li>Results will automatically refresh while you type.</li>
                    <li>Use the page numbers below the results to go through more records.</li>
                    <li>To download data, click the <strong>Download Excel</strong> button next to the search box.</li>
                </ol>

                <h5>Excel Download Columns:</h5>
                <div class="row">
                    <div class="col-md-4">
                        <ul>
                            <li>Mobile Number</li>
                            <li>Previous Due</li>
                            <li>Payments</li>
                            <li>Total Usage</li>
                            <li>IDD</li>
                            <li>Roaming</li>
                            <li>VAS</li>
                            <li>Discounts</li>
                            <li>Balance Adj.</li>
                        </ul>
                    </div>
                    <div class="col-md-4">
                        <ul>
                            <li>Commitment Charges</li>
                            <li>Late Payment</li>
                            <li>Gov Taxes</li>
                            <li>VAT</li>
                            <li>Add to Bill</li>
                            <li>Bill Charges</li>
                            <li>Total Payable</li>
                            <li>Company Contribution</li>
                            <li>Voice Data</li>
                        </ul>
                    </div>
                    <div class="col-md-4">
                        <ul>
                            <li>Employee</li>
                            <li>Designation</li>
                            <li>Hierarchy</li>
                            <li>NIC</li>
                            <li>HRIS</li>
                            <li>Salary Deduction</li>
                            <li>Billing Month</li>
                        </ul>
                    </div>
                </div>

                <h5>Salary Deduction Formula:</h5>
                <p>The salary deduction is calculated using the following logic:</p>
                <pre><code>
                    X = Total Amount Payable - Company Contribution  
                    Y = Roaming + VAS + Add to Bill  

                    If X < Y:
                        Salary Deduction = Y
                    Else:
                        Salary Deduction = X
                </code></pre>
                <p>This formula ensures that the employee pays at least the variable costs (Roaming, VAS, and Add to Bill), even if the company contribution reduces the payable amount below that.</p>

                <h5>Tips:</h5>
                <ul>
                    <li>Search filters apply to all fields shown.</li>
                    <li>Be sure to scroll horizontally if you are on a small screen to see all data columns.</li>
                    <li>If you get stuck, refresh the page and search again.</li>
                </ul>
                <hr class="my-5">
            </div>

            <div class="section" id="section4">
                <h2>4. Mobile Bill Report – Finance</h2>
                <p>This report is specifically for finance use. It shows mobile bill records filtered by the <strong>Update Date</strong> and includes employee and billing details.</p>

                <h5>Steps:</h5>
                <ol>
                    <li>Navigate to the <strong>Mobile Bill Report - Finance</strong> page from the menu.</li>
                    <li>Select a value from the <strong>Update Date</strong> dropdown list.</li>
                    <li>The system will automatically reload and display data for the selected date.</li>
                    <li>To download the data as Excel, click the <strong>Download Excel</strong> button.</li>
                    <li>If no date is selected, a message will prompt you to select a month before downloading.</li>
                </ol>

                <h5>Excel Download Columns:</h5>
                <div class="row">
                    <div class="col-md-4">
                        <ul>
                            <li>Mobile Number</li>
                            <li>Previous Due</li>
                            <li>Payments</li>
                            <li>Total Usage</li>
                            <li>IDD</li>
                            <li>Roaming</li>
                            <li>VAS</li>
                            <li>Discounts</li>
                            <li>Balance Adj.</li>
                        </ul>
                    </div>
                    <div class="col-md-4">
                        <ul>
                            <li>Commitment Charges</li>
                            <li>Late Payment</li>
                            <li>Gov Taxes</li>
                            <li>VAT</li>
                            <li>Add to Bill</li>
                            <li>Bill Charges</li>
                            <li>Total Payable</li>
                            <li>Voice Data</li>
                        </ul>
                    </div>
                    <div class="col-md-4">
                        <ul>
                            <li>Employee</li>
                            <li>Designation</li>
                            <li>Hierarchy</li>
                            <li>NIC</li>
                            <li>HRIS</li>
                            <li>Billing Month</li>
                        </ul>
                    </div>
                </div>

                <h5>Tips:</h5>
                <ul>
                    <li>Ensure you select a valid <strong>Update Date</strong> before downloading.</li>
                    <li>Use pagination at the bottom to browse through more records if available.</li>
                    <li>If data doesn’t load, ensure your internet connection is active and refresh the page.</li>
                </ul>
                <hr class="my-5">
            </div>
            <div class="section" id="section5">
                <h2>5. Mobile Bill Report – Police</h2>
                <p>This section provides a specialized mobile bill report filtered for police-related connections. It supports dynamic searching and downloading of billing data related to police personnel.</p>

                <h5>Steps:</h5>
                <ol>
                    <li>Navigate to the <strong>Mobile Bill Report - Police</strong> page from the menu.</li>
                    <li>The table will show only police alarm / fire connections only.</li>
                    <li>Type a search keyword in the box. You can search by mobile number, name, HRIS number, NIC, or billing month.</li>
                    <li>The table will update live as you type.</li>
                    <li>To download the filtered results, click the <strong>Download Excel</strong> button.</li>
                    <li>Pagination links at the bottom allow you to browse through additional pages.</li>
                </ol>

                <h5>Excel Download Columns:</h5>
                <div class="row">
                    <div class="col-md-4">
                        <ul>
                            <li>Mobile Number</li>
                            <li>Previous Due</li>
                            <li>Payments</li>
                            <li>Total Usage</li>
                            <li>IDD</li>
                            <li>Roaming</li>
                            <li>VAS</li>
                            <li>Discounts</li>
                            <li>Balance Adj.</li>
                        </ul>
                    </div>
                    <div class="col-md-4">
                        <ul>
                            <li>Commitment Charges</li>
                            <li>Late Payment</li>
                            <li>Gov Taxes</li>
                            <li>VAT</li>
                            <li>Add to Bill</li>
                            <li>Bill Charges</li>
                            <li>Total Payable</li>
                            <li>Company Contribution</li>
                            <li>Voice Data</li>
                        </ul>
                    </div>
                    <div class="col-md-4">
                        <ul>
                            <li>Employee</li>
                            <li>Designation</li>
                            <li>Hierarchy</li>
                            <li>NIC</li>
                            <li>HRIS</li>
                            <li>Salary Deduction</li>
                            <li>Billing Month</li>
                        </ul>
                    </div>
                </div>

                <h5>Tips:</h5>
                <ul>
                    <li>Partial keywords are accepted (e.g., just the first few digits of HRIS or mobile number).</li>
                    <li>Use lowercase or uppercase – search is not case-sensitive.</li>
                    <li>Check for spelling errors if no results are found.</li>
                </ul>
                <hr class="my-5">
            </div>
            <div class="section" id="section6">
                <h2>6. Resignation List Report</h2>
                <p>This section displays a searchable and paginated list of employees who have resigned. It helps HR or admin staff view details and manage mobile disconnections when necessary.</p>

                <h5>Steps:</h5>
                <ol>
                    <li>Navigate to the <strong>Resignation List Report</strong> page from the menu.</li>
                    <li>Use the search box to look up employees by HRIS, Name, NIC, or Branch.</li>
                    <li>Results will automatically update as you type.</li>
                    <li>Click on any row in the table to view resignation details in a modal pop-up.</li>
                </ol>

                <h5>Modal Window Details:</h5>
                <ul>
                    <li>When a row is clicked, a modal will appear showing the employee's full resignation and employment details.</li>
                    <li>The fields shown include HRIS, Name, NIC, Designation, Department, Branch, Date of Joining, Category, Type, Effective Date, Resignation Type, Reason, Mobile Number, and Voice/Data.</li>
                    <li>If the record contains a mobile number, a red <strong>“Disconnect Connection”</strong> button will appear at the bottom left.</li>
                </ul>

                <h5>Disconnecting a Mobile Connection:</h5>
                <ol>
                    <li>Click the <strong>Disconnect Connection</strong> button in the modal.</li>
                    <li>A confirmation alert will appear to confirm disconnection for the displayed mobile number.</li>
                    <li>Once confirmed:
                        <ul>
                            <li>The system will update the connection status to <code>disconnected</code> in the database.</li>
                            <li>The <code>disconnection_date</code> will be automatically set to the current date/time.</li>
                            <li>If successful, a success message will appear and the modal will close.</li>
                            <li>The list will refresh to reflect the disconnection.</li>
                        </ul>
                    </li>
                    <li>If there's an error or the connection is already marked as disconnected, an appropriate message will be shown instead.</li>
                </ol>

                <h5>Tips:</h5>
                <ul>
                    <li>Use partial names or HRIS numbers for quick filtering.</li>
                    <li>If the modal fails to close, press <kbd>Esc</kbd> or refresh the page.</li>
                    <li>Ensure you have a stable connection when performing disconnections.</li>
                </ul>
                <hr class="my-5">
            </div>

            <div class="section" id="section7">
                <h2>7. Disconnected Mobile Numbers Report</h2>
                <p>This report provides a list of employees whose mobile numbers have been marked as <strong>disconnected</strong> in the system. It includes both currently and historically disconnected numbers.</p>

                <h5>Steps:</h5>
                <ol>
                    <li>Navigate to the <strong>Disconnected Mobile Numbers Report</strong> page via the menu.</li>
                    <li>Use the date filters at the top to narrow the report by <strong>Disconnection Date</strong>.</li>
                    <li>Click the <strong>Filter</strong> button to apply your selected date range.</li>
                    <li>To export the result as an Excel file, click <strong>Download Excel</strong>.</li>
                </ol>

                <h5>Report Columns:</h5>
                <div class="row">
                    <div class="col-md-6">
                        <ul>
                            <li>HRIS</li>
                            <li>Name</li>
                            <li>Company Hierarchy</li>
                            <li>Designation</li>
                            <li>Mobile Number</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <ul>
                            <li>Voice / Data</li>
                            <li>Date Joined</li>
                            <li>Date Resigned</li>
                            <li>Connection Status</li>
                            <li>Disconnection Date</li>
                        </ul>
                    </div>
                </div>

                <h5>Tips:</h5>
                <ul>
                    <li>You can filter by a single date (only <strong>From</strong>) or a full range (From and To).</li>
                    <li>If no records show up, check that the selected date range is correct and that disconnections were recorded.</li>
                </ul>
                <hr class="my-5">
            </div>

            <div class="section" id="section8">
                <h2>8. Upload Employee Master CSV</h2>
                <p>This feature allows uploading the full employee master record in bulk using a formatted CSV file. Each row represents an employee's core HR and organizational details.</p>

                <h5>Steps:</h5>
                <ol>
                    <li>Go to the <strong>Upload Employee CSV</strong> page from the menu.</li>
                    <li>Click <strong>Select CSV File</strong> and choose the file from your system.</li>
                    <li>Click the <strong>Upload</strong> button.</li>
                    <li>If the file is valid and uploaded successfully, a success message will be shown.</li>
                    <li>If there are issues, detailed logs will be written in the system log file: <code>upload_log.txt</code>.</li>
                </ol>

                <h5>Important Notes:</h5>
                <ul>
                    <li><strong>The first row (header row) must be included in your CSV file.</strong> It is automatically skipped during import.</li>
                    <li>Only <code>.csv</code> files are accepted.</li>
                    <li>Each column in the file must follow the correct order and not be left blank for mandatory fields.</li>
                </ul>

                <h5>Required CSV Column Order:</h5>
                <div class="row">
                    <div class="col-md-6">
                        <ul>
                            <li>HRIS</li>
                            <li>EPF No</li>
                            <li>Company Hierarchy</li>
                            <li>Title</li>
                            <li>Full Name</li>
                            <li>Designation</li>
                            <li>Display Name</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <ul>
                            <li>Location</li>
                            <li>NIC No</li>
                            <li>Category</li>
                            <li>Employment Type</li>
                            <li>Date Joined</li>
                            <li>Ops/Sales Category</li>
                            <li>Status</li>
                        </ul>
                    </div>
                </div>

                <h5>Tips:</h5>
                <ul>
                    <li>Use Excel or Google Sheets to prepare and export the file as CSV format.</li>
                    <li>Empty rows in the CSV will be ignored automatically.</li>
                </ul>
                <hr class="my-5">
            </div>

            <div class="section" id="section9">
                <h2>9. Upload Inactive Employee CSV</h2>
                <p>This tool allows HR/Admin users to upload a CSV file containing data of <strong>inactive or resigned employees</strong>. The data is inserted into the main employee details table with disconnection/resignation information.</p>

                <h5>Steps:</h5>
                <ol>
                    <li>Go to the <strong>Upload Inactive Employee CSV</strong> page from the menu.</li>
                    <li>Click <strong>Select CSV File</strong> and choose your CSV file.</li>
                    <li>Click the <strong>Upload</strong> button.</li>
                    <li>If successful, a confirmation message will appear.</li>
                    <li>If there's an error, a detailed message will be shown and logged in <code>upload_log.txt</code>.</li>
                </ol>

                <h5>CSV Format Requirements:</h5>
                <ul>
                    <li><strong>The first row (header) must be present in your CSV file.</strong> It is automatically skipped during import.</li>
                    <li>The file must be in valid <code>.csv</code> format and follow the required column order.</li>
                </ul>

                <h5>Required Column Order:</h5>
                <div class="row">
                    <div class="col-md-6">
                        <ul>
                            <li>HRIS</li>
                            <li>EPF No</li>
                            <li>Company Hierarchy</li>
                            <li>Title</li>
                            <li>Full Name</li>
                            <li>Designation</li>
                            <li>Display Name</li>
                            <li>Location</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <ul>
                            <li>NIC No</li>
                            <li>Category</li>
                            <li>Employment Type</li>
                            <li>Date Joined</li>
                            <li>Date Resigned</li>
                            <li>Ops/Sales Category</li>
                            <li>Status</li>
                        </ul>
                    </div>
                </div>

                <h5>Validation & Logging:</h5>
                <ul>
                    <li>Empty rows will be skipped automatically.</li>
                    <li>If a row fails to insert, the system logs the error with the row number and reason.</li>
                    <li>Upload activity is logged in <code>upload_log.txt</code> with timestamps.</li>
                    <li>All file upload errors (like file too large, partial upload, etc.) are logged and shown to the user.</li>
                </ul>
                <h5>Tips:</h5>
                <ul>
                    <li>Prepare your data in Excel or Google Sheets and export as CSV.</li>
                    <li>Review <code>upload_log.txt</code> after upload to confirm status and errors.</li>
                    <li>Always include a proper <strong>Date Resigned</strong> value for resigned employees.</li>
                </ul>
                <hr class="my-5">
            </div>

            <div class="section" id="section10">
                <h2>10. Upload Resignation List CSV</h2>
                <p>This page allows you to upload a CSV file containing a list of employees who have resigned. The data will be stored in the <code>tbl_admin_employee_resignations</code> table for use in reports and system workflows.</p>

                <h5>Steps:</h5>
                <ol>
                    <li>Go to the <strong>Upload Resignation CSV</strong> page from the menu.</li>
                    <li>Click <strong>Select CSV File</strong> and choose your resignation list file.</li>
                    <li>Click the <strong>Upload</strong> button.</li>
                    <li>If the upload is successful, a green confirmation message will appear.</li>
                    <li>If there's an issue with any row, a red error will appear identifying the problematic row and reason.</li>
                </ol>
                <h5>CSV Format Requirements:</h5>
                <ul>
                    <li><strong>The first row must contain the header and will be automatically skipped.</strong></li>
                    <li><strong>Most Important:</strong>
                        <ul>
                            <li>Before creating the upload CSV file:
                                <ul>
                                    <li>Delete the <strong>first column</strong> titled <em>No</em>.</li>
                                    <li>Delete the following columns if they exist:
                                        <ul>
                                            <li>Exit Interview Yes/No</li>
                                            <li>Contact no</li>
                                            <li>Available Lease Facility</li>
                                            <li>Staff Uniform/Corporate Shirt (Amt)</li>
                                            <li>Staff Loan to be settle (Amt)</li>
                                            <li>Festival Advance (Amt)</li>
                                            <li>Other Loans (Credit Card)</li>
                                            <li>Medical Loan (Amt)</li>
                                            <li>Mobile Outstanding or Allowance</li>
                                            <li>Gift Outstanding</li>
                                            <li>Client Code</li>
                                        </ul>
                                    </li>
                                    <li>Make sure the <strong>HRIS</strong> column is 6 digits for all entries:
                                        <ul>
                                            <li>In Excel, select the HRIS column.</li>
                                            <li>Right-click and choose <strong>Format Cells</strong>.</li>
                                            <li>Go to <strong>Custom</strong> format.</li>
                                            <li>Enter <code>000000</code> as the format (six zeros).</li>
                                        </ul>
                                    </li>
                                    <li>Remove all <strong>yellow-highlighted rows</strong> or lines that have no data.</li>
                                </ul>
                            </li>
                        </ul>
                    </li>
                    <li>The file must be in standard <code>.csv</code> format with comma-separated values.</li>
                    <li>Blank rows will be ignored.</li>
                    <li>The script automatically removes hidden BOM characters from the first column.</li>
                </ul>

                <h5>Required Column Order:</h5>
                <div class="row">
                    <div class="col-md-6">
                        <ul>
                            <li>Index (ignored)</li>
                            <li>HRIS</li>
                            <li>Title</li>
                            <li>Name</li>
                            <li>Designation</li>
                            <li>Department</li>
                            <li>Branch</li>
                            <li>Date of Joining (DOJ)</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <ul>
                            <li>NIC</li>
                            <li>Category</li>
                            <li>Employment Type</li>
                            <li>Resignation Effective Date</li>
                            <li>Resignation Type</li>
                            <li>Reason</li>
                        </ul>
                    </div>
                </div>

                <h5>Validation & Upload Behavior:</h5>
                <ul>
                    <li>The system skips and ignores the header row and any empty rows.</li>
                    <li>If any row fails to insert, an error message with row number and database error will be shown immediately.</li>
                    <li>All text fields are trimmed and cleaned before insertion.</li>
                </ul>
                <h5>Tips:</h5>
                <ul>
                    <li>Use Excel or Google Sheets to prepare your resignation list, and export as <strong>CSV (Comma delimited)</strong>.</li>
                    <li>Always include a proper resignation effective date and reason for every employee.</li>
                    <li>If errors appear, correct them in the source file and try uploading again.</li>
                </ul>
                <hr class="my-5">
            </div>

            <div class="section" id="section11">
                <h2>11. Submit SIM / Mobile / Transfer Request</h2>
                <p>This section explains how employees or admins can submit a new SIM, mobile phone, or transfer request through the system.</p>

                <h5>Steps:</h5>
                <ol>
                    <li>Navigate to the <strong>New SIM/Mobile/Transfer Request</strong> page from the side menu.</li>
                    <li>Select the <strong>Request Type</strong> from the dropdown (SIM Request, Mobile Phone Request, or Transfer).</li>
                    <li>Enter the <strong>HRIS number</strong> (must be 6 digits).</li>
                    <li>If valid, the system will auto-fill employee details like Name, NIC, Designation, etc.</li>
                    <li>If a mobile number or phone is already issued, the system will show a confirmation modal to proceed or cancel.</li>
                    <li>Fill in any additional required fields like <strong>Voice/Data</strong> selection and <strong>Email</strong>.</li>
                    <li>Click the <strong>Submit Request</strong> button.</li>
                </ol>

                <h5>Behavior Based on Request Type:</h5>
                <ul>
                    <li><strong>SIM Request:</strong> All form fields are visible, including Voice/Data selection and package details.</li>
                    <li><strong>Mobile Phone Request:</strong> Only basic fields (Name, NIC, Designation, Email, etc.) are needed. Voice/Data options are hidden.</li>
                    <li><strong>Transfer Request:</strong> Similar to SIM request, but used when transferring existing mobile numbers to another employee/division.</li>
                </ul>

                <h5>Validation Rules:</h5>
                <ul>
                    <li>HRIS number must be exactly 6 digits.</li>
                    <li>Email field is mandatory for all request types.</li>
                    <li>System checks if the user already has mobile numbers or phones issued before allowing submission.</li>
                </ul>

                <h5>Tips:</h5>
                <ul>
                    <li>If the HRIS is invalid, a modal alert will appear and the form will not continue.</li>
                    <li>Use the Enter key to validate the HRIS quickly, or tab out of the field.</li>
                    <li>Always double-check the auto-filled employee data before submitting.</li>
                    <li>If unsure about the voice/data package, select “Other” and specify the GB required.</li>
                </ul>

                <hr class="my-5">
            </div>


            <div class="section" id="section12">
                <h2>12. Recommend SIM / Mobile / Transfer Requests</h2>
                <p>This section is designed for <strong>Cluster Leaders, ASM/RMs, and Division Heads</strong> to review and recommend pending employee requests for SIM cards, mobile phones, or user transfers.</p>

                <h5>Access:</h5>
                <ul>
                    <li>Only users with the roles <code>asm_rm</code>, <code>cluster_leader</code>, or <code>division_head</code> can access this section.</li>
                    <li>Requests shown are filtered based on your assigned <strong>request division</strong>.</li>
                </ul>

                <h5>Steps:</h5>
                <ol>
                    <li>Go to the <strong>Recommend Requests</strong> page from the sidebar.</li>
                    <li>You will see a table with all pending requests from your division that have not yet been recommended.</li>
                    <li>Review each request's information including HRIS, Name, Request Type, Designation, Branch, and requested packages.</li>
                    <li>Click on any row to view if the employee already has issued phones or mobile numbers.</li>
                    <li>Click <strong>Approve</strong> or <strong>Reject</strong> using the buttons in the <em>Action</em> column.</li>
                    <li>A confirmation dialog will appear to prevent accidental submissions.</li>
                    <li>Once confirmed, the request will be marked as recommended or rejected accordingly.</li>
                </ol>

                <h5>Behavior:</h5>
                <ul>
                    <li>Approved requests will update the <code>recommended_by</code> field with your username.</li>
                    <li>Rejected requests will update the same field with <code>REJECTED by [username]</code>.</li>
                    <li>Rejected or approved requests will no longer appear in the pending list.</li>
                </ul>

                <h5>Helpful Features:</h5>
                <ul>
                    <li><strong>Clicking a row:</strong> Triggers a modal popup showing mobile or phone details for that employee.</li>
                    <li><strong>No existing connections:</strong> If the employee has no issued phones or numbers, a “No Records” modal will appear.</li>
                    <li><strong>Responsive Table:</strong> Horizontal scroll enabled for smaller screens.</li>
                </ul>

                <h5>Tips:</h5>
                <ul>
                    <li>Review all package and data fields before taking action.</li>
                    <li>If no rows appear, there are no pending requests for your division.</li>
                    <li>Use the modals to verify existing connections before approving a new one.</li>
                </ul>
                <hr class="my-5">
            </div>
            <a href="#" class="back-to-top">Back to Top</a>
        </div>
    </div>
</div>
<script>
    const backToTop = document.querySelector('.back-to-top');
    window.addEventListener('scroll', () => {
        backToTop.style.display = window.scrollY > 300 ? 'block' : 'none';
    });
</script>
</body>
</html>
