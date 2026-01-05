<?php
// ajax-employee-table.php
include 'nocache.php'; 
include 'connections/connection.php';

$limit  = 10;
$page   = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$offset = ($page - 1) * $limit;

$sql = "SELECT 
    t1.mobile_no,
    t1.hris_no,
    t1.epf_no,
    t1.voice_data,
    t1.title,
    t1.name_of_employee,
    t1.nic_no,
    t1.company_hierarchy,
    t1.designation,
    t1.location,
    t1.date_joined,
    t1.category_ops_sales,
    t1.employment_categories,
    (
        SELECT c1.contribution_amount
        FROM tbl_admin_hris_contributions c1
        WHERE c1.hris_no = t1.hris_no
          AND c1.mobile_no = t1.mobile_no
          AND c1.effective_from = (
              SELECT MAX(c2.effective_from)
              FROM tbl_admin_hris_contributions c2
              WHERE c2.hris_no = t1.hris_no
                AND c2.mobile_no = t1.mobile_no
          )
        LIMIT 1
    ) AS company_contribution,
    t1.remarks,
    t1.status,
    t1.connection_status
FROM tbl_admin_mobile_issues t1
WHERE t1.name_of_employee LIKE '%$search%' 
   OR t1.nic_no LIKE '%$search%' 
   OR t1.mobile_no LIKE '%$search%' 
   OR t1.hris_no LIKE '%$search%' 
ORDER BY t1.id DESC
LIMIT $limit OFFSET $offset";

$result = $conn->query($sql);

$count_sql = "SELECT COUNT(*) as total FROM tbl_admin_mobile_issues 
              WHERE name_of_employee LIKE '%$search%' 
              OR nic_no LIKE '%$search%' 
              OR hris_no LIKE '%$search%' 
              OR mobile_no LIKE '%$search%'";
$count_result = $conn->query($count_sql);
$row = $count_result->fetch_assoc();
$total_rows = (int)$row['total'];
$total_pages = max(1, (int)ceil($total_rows / $limit));
?>
<div class="table-responsive font-size">
  <table class="table table-bordered table-striped align-middle text-start" id="employeeTable">
    <thead class="table-primary text-start">
      <tr>
        <th>Mobile No</th>
        <th>HRIS No</th>
        <th>EPF No</th>
        <th>Voice/Data</th>
        <th>Title</th>
        <th>Full Name</th>
        <th>NIC No</th>
        <th>Company Hierarchy</th>
        <th>Designation</th>
        <th>Location</th>
        <th>Date Joined</th>
        <th>Type</th>
        <th>Category</th>
        <th>Company Contribution</th>
        <th>Remarks</th>
        <th>Status</th>
        <th>Connection Status</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($result && $result->num_rows > 0): ?>
        <?php while ($r = $result->fetch_assoc()): ?>
          <tr class="row-clickable"
              data-mobile="<?= htmlspecialchars($r['mobile_no'] ?? '') ?>"
              data-hris="<?= htmlspecialchars($r['hris_no'] ?? '') ?>"
              data-epf="<?= htmlspecialchars($r['epf_no'] ?? '') ?>"
              data-voicedata="<?= htmlspecialchars($r['voice_data'] ?? '') ?>"
              data-title="<?= htmlspecialchars($r['title'] ?? '') ?>"
              data-fullname="<?= htmlspecialchars($r['name_of_employee'] ?? '') ?>"
              data-nic="<?= htmlspecialchars($r['nic_no'] ?? '') ?>"
              data-company="<?= htmlspecialchars($r['company_hierarchy'] ?? '') ?>"
              data-designation="<?= htmlspecialchars($r['designation'] ?? '') ?>"
              data-location="<?= htmlspecialchars($r['location'] ?? '') ?>"
              data-joined="<?= htmlspecialchars($r['date_joined'] ?? '') ?>"
              data-type="<?= htmlspecialchars($r['employment_categories'] ?? '') ?>"
              data-category="<?= htmlspecialchars($r['category_ops_sales'] ?? '') ?>"
              data-contribution="<?= htmlspecialchars($r['company_contribution'] ?? '0.00') ?>"
              data-remarks="<?= htmlspecialchars($r['remarks'] ?? '') ?>"
              data-status="<?= htmlspecialchars($r['status'] ?? '') ?>"
              data-connectionstatus="<?= htmlspecialchars($r['connection_status'] ?? '') ?>">
            <td><?= htmlspecialchars($r['mobile_no'] ?? '') ?></td>
            <td><?= htmlspecialchars($r['hris_no'] ?? '') ?></td>
            <td><?= htmlspecialchars($r['epf_no'] ?? '') ?></td>
            <td><?= htmlspecialchars($r['voice_data'] ?? '') ?></td>
            <td><?= htmlspecialchars($r['title'] ?? '') ?></td>
            <td><?= htmlspecialchars($r['name_of_employee'] ?? '') ?></td>
            <td><?= htmlspecialchars($r['nic_no'] ?? '') ?></td>
            <td><?= htmlspecialchars($r['company_hierarchy'] ?? '') ?></td>
            <td><?= htmlspecialchars($r['designation'] ?? '') ?></td>
            <td><?= htmlspecialchars($r['location'] ?? '') ?></td>
            <td><?= htmlspecialchars($r['date_joined'] ?? '') ?></td>
            <td><?= htmlspecialchars($r['employment_categories'] ?? '') ?></td>
            <td><?= htmlspecialchars($r['category_ops_sales'] ?? '') ?></td>
            <td><?= number_format((float)($r['company_contribution'] ?? 0), 2) ?></td>
            <td><?= htmlspecialchars($r['remarks'] ?? '') ?></td>
            <td><?= empty($r['status']) ? 'Active' : htmlspecialchars($r['status']) ?></td>
            <td><?= htmlspecialchars($r['connection_status'] ?? '') ?></td>
          </tr>
        <?php endwhile; ?>
      <?php else: ?>
        <tr>
          <td colspan="17" class="text-center text-danger">No records found.</td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>

  <!-- Pagination -->
  <div class="d-flex justify-content-end">
    <nav aria-label="Employee pagination">
      <ul class="pagination">
        <?php if ($page > 1): ?>
          <li class="page-item">
            <a class="page-link" href="#" role="button"
               data-page="<?= $page - 1 ?>"
               data-skip-loader="1"
               aria-label="Previous">Previous</a>
          </li>
        <?php endif; ?>

        <?php
          $max_links = 3;
          $start = max(1, $page - 1);
          $end = min($total_pages, $start + $max_links - 1);
          if ($end - $start < $max_links - 1) $start = max(1, $end - $max_links + 1);

          if ($start > 1):
        ?>
          <li class="page-item">
            <a class="page-link" href="#" role="button" data-page="1" data-skip-loader="1">1</a>
          </li>
          <?php if ($start > 2): ?>
            <li class="page-item disabled"><span class="page-link">…</span></li>
          <?php endif; ?>
        <?php endif; ?>

        <?php for ($i = $start; $i <= $end; $i++): ?>
          <li class="page-item <?= ($i == $page) ? 'active' : '' ?>">
            <a class="page-link" href="#" role="button"
               data-page="<?= $i ?>"
               data-skip-loader="1"><?= $i ?></a>
          </li>
        <?php endfor; ?>

        <?php if ($end < $total_pages): ?>
          <?php if ($end < $total_pages - 1): ?>
            <li class="page-item disabled"><span class="page-link">…</span></li>
          <?php endif; ?>
          <li class="page-item">
            <a class="page-link" href="#" role="button"
               data-page="<?= $total_pages ?>"
               data-skip-loader="1"><?= $total_pages ?></a>
          </li>
        <?php endif; ?>

        <?php if ($page < $total_pages): ?>
          <li class="page-item">
            <a class="page-link" href="#" role="button"
               data-page="<?= $page + 1 ?>"
               data-skip-loader="1"
               aria-label="Next">Next</a>
          </li>
        <?php endif; ?>
      </ul>
    </nav>
  </div>
</div>
