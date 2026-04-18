<?php
if (!isset($pageTitle)) $pageTitle = 'E-Wallet';
$currentUser = isLoggedIn() ? getCurrentUser() : null;
$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= sanitize($pageTitle) ?> - E-Wallet</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/finalweb/assets/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand fw-bold" href="/finalweb/index.php">
                <i class="bi bi-wallet2"></i> E-Wallet
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <?php if ($currentUser): ?>
                    <ul class="navbar-nav me-auto">
                        <?php if ($currentUser['role'] === 'user'): ?>
                            <li class="nav-item"><a class="nav-link" href="/finalweb/dashboard.php">Dashboard</a></li>
                            <li class="nav-item"><a class="nav-link" href="/finalweb/profile.php">Profile</a></li>
                            <li class="nav-item"><a class="nav-link" href="/finalweb/deposit.php">Deposit</a></li>
                            <li class="nav-item"><a class="nav-link" href="/finalweb/withdraw.php">Withdraw</a></li>
                            <li class="nav-item"><a class="nav-link" href="/finalweb/transfer.php">Transfer</a></li>
                            <li class="nav-item"><a class="nav-link" href="/finalweb/phone_card.php">Phone Card</a></li>
                            <li class="nav-item"><a class="nav-link" href="/finalweb/transactions.php">History</a></li>
                        <?php elseif ($currentUser['role'] === 'admin'): ?>
                            <li class="nav-item"><a class="nav-link" href="/finalweb/admin/dashboard.php">Dashboard</a></li>
                            <li class="nav-item"><a class="nav-link" href="/finalweb/admin/accounts.php">Accounts</a></li>
                            <li class="nav-item"><a class="nav-link" href="/finalweb/admin/pending_transactions.php">Pending Transactions</a></li>
                        <?php endif; ?>
                    </ul>
                    <ul class="navbar-nav">
                        <li class="nav-item">
                            <span class="nav-link text-light">
                                <i class="bi bi-person-circle"></i> <?= sanitize($currentUser['full_name']) ?>
                            </span>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/finalweb/change_password.php"><i class="bi bi-key"></i> Change Password</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/finalweb/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
                        </li>
                    </ul>
                <?php else: ?>
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item"><a class="nav-link" href="/finalweb/login.php">Login</a></li>
                        <li class="nav-item"><a class="nav-link" href="/finalweb/register.php">Register</a></li>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <?php if ($flash): ?>
            <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : $flash['type'] ?> alert-dismissible fade show">
                <?= $flash['message'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
