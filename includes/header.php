<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($pageTitle ?? APP_NAME) ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
  * { box-sizing: border-box; }
  body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; }
  [x-cloak] { display: none !important; }

  /* Sidebar nav links */
  .nav-link {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 12px;
    border-radius: 10px;
    font-size: 13.5px;
    font-weight: 500;
    color: #4b5563;
    text-decoration: none;
    transition: background 0.12s, color 0.12s;
    white-space: nowrap;
  }
  .nav-link:hover {
    background: #f3f4f6;
    color: #111827;
  }
  .nav-link.active {
    background: #eef2ff;
    color: #4338ca;
    font-weight: 600;
  }
  .nav-link .icon {
    width: 18px;
    text-align: center;
    font-size: 14px;
    flex-shrink: 0;
    opacity: 0.7;
  }
  .nav-link.active .icon { opacity: 1; }

  /* Cards */
  .ff-card {
    background: #fff;
    border-radius: 16px;
    border: 1px solid #e5e7eb;
  }

  /* Stat card hover */
  .stat-card { transition: box-shadow 0.15s, transform 0.15s; }
  .stat-card:hover { box-shadow: 0 4px 20px rgba(0,0,0,.06); transform: translateY(-1px); }

  /* Table hover */
  .ff-table tr:hover td { background: #fafafa; }

  /* Smooth transitions */
  .ff-transition { transition: all 0.2s cubic-bezier(.4,0,.2,1); }

  /* Badge styles */
  .badge { display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 20px; font-size: 11.5px; font-weight: 600; line-height: 1.6; }
  
  /* Scrollbar */
  ::-webkit-scrollbar { width: 4px; height: 4px; }
  ::-webkit-scrollbar-track { background: transparent; }
  ::-webkit-scrollbar-thumb { background: #e5e7eb; border-radius: 99px; }
  ::-webkit-scrollbar-thumb:hover { background: #d1d5db; }
</style>
<script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="h-full bg-gray-50 text-gray-900 antialiased">
