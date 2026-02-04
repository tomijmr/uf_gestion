<?php require_once __DIR__ . '/../../app/helpers.php'; ?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e(getenv('APP_NAME') ?: 'Gestión - Universal Fitness') ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  
  <!-- Favicons -->
  <?php 
  $favicon_base = rtrim(str_replace('/views/partials', '/public', dirname($_SERVER['SCRIPT_NAME'])), '/');
  ?>
  <link rel="icon" type="image/svg+xml" href="<?= $favicon_base ?>/favicon.svg" />
  <link rel="icon" type="image/png" sizes="96x96" href="<?= $favicon_base ?>/favicon-96x96.png" />
  <link rel="shortcut icon" href="<?= $favicon_base ?>/favicon.ico" />
  <link rel="apple-touch-icon" sizes="180x180" href="<?= $favicon_base ?>/apple-touch-icon.png" />
  <meta name="apple-mobile-web-app-title" content="UF Gestión" />
  <link rel="manifest" href="<?= $favicon_base ?>/site.webmanifest" />
  <meta name="theme-color" content="#0d6efd" />
</head>
<body class="bg-light">
