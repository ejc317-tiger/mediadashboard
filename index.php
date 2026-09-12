<?php
declare(strict_types=1);
require __DIR__ . '/auth.php';
requireLogin();
$userStatement = database()->prepare('SELECT display_name FROM users WHERE id = ?');
$userStatement->execute([$_SESSION['user_id']]);
$displayName = (string) ($userStatement->fetchColumn() ?: 'User');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Northstar — Investment Intelligence</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="styles.css">
</head>
<body>
  <aside class="sidebar">
    <a class="brand" href="#" aria-label="Northstar home">
      <span class="brand-mark"><i></i><i></i><i></i></span>
      <span>northstar</span>
    </a>
    <nav aria-label="Primary navigation">
      <p class="nav-label">Workspace</p>
      <button class="nav-item active" data-view="overview"><span class="icon">⌂</span>Overview</button>
      <button class="nav-item" data-view="allcompanies"><span class="icon">▦</span>All companies</button>
      <button class="nav-item" data-view="pefirms"><span class="icon">◈</span>Private equity firms</button>
      <button class="nav-item" data-view="vcfirms"><span class="icon">◇</span>VC companies</button>
      <button class="nav-item" data-view="aicompanies"><span class="icon">✧</span>AI companies</button>
      <button class="nav-item" data-view="datacenters"><span class="icon">⌖</span>Datacenter map</button>
      <button class="nav-item" data-view="spacs"><span class="icon">◎</span>SPACs</button>
      <p class="nav-label second">Intelligence</p>
      <button class="nav-item" id="navAiResearch"><span class="icon">✦</span>AI Research</button>
      <button class="nav-item"><span class="icon">◴</span>Saved searches</button>
    </nav>
    <div class="sidebar-foot">
      <button class="help"><span>?</span>Help center</button>
      <div class="profile"><div class="avatar"><?=htmlspecialchars(strtoupper(substr($displayName,0,2)))?></div><div><strong><?=htmlspecialchars($displayName)?></strong><small>Authenticated</small></div><a href="logout.php" title="Sign out">↪</a></div>
    </div>
  </aside>

  <main>
    <header class="topbar">
      <div class="crumb"><span>Northstar</span><b>/</b><strong id="pageCrumb">Overview</strong></div>
      <div class="top-actions">
        <button class="icon-btn" aria-label="Notifications">♢<i></i></button>
        <button class="outline" id="addCompany">＋ Add company</button>
        <button class="primary" id="askAi">✦ Ask Northstar AI</button>
      </div>
    </header>

    <section class="content" id="overviewView">
      <div class="welcome-row">
        <div><p class="eyebrow"><?=strtoupper(date('l, F j'))?></p><h1>Welcome, <?=htmlspecialchars($displayName)?>.</h1><p class="subtitle">Here’s what’s happening across your coverage universe.</p></div>
        <div class="updated"><span></span><span id="syncLabel">Connecting to database…</span></div>
      </div>

      <section class="ai-search">
        <div class="spark">✦</div>
        <div class="ai-copy"><strong>Ask anything across your data</strong><span>Search companies, data centers, transactions, and SPACs in natural language</span></div>
        <form id="aiForm"><input id="aiInput" aria-label="Ask Northstar AI" placeholder="e.g. Show PE-owned software companies with $100M+ revenue…"><button>Ask AI <span>↗</span></button></form>
        <div class="suggestions"><span>Try asking:</span><button>AI infrastructure targets</button><button>SPACs expiring in 12 months</button><button>European data center capacity</button></div>
      </section>
      <div class="data-notice"><span>ⓘ</span><p><strong>Live workspace</strong> — Database updates are source-gated and marked for review until an analyst verifies the linked disclosure.</p><button id="dismissNotice" aria-label="Dismiss">×</button></div>

      <div class="section-head"><div><h2>Market pulse</h2><p>Key metrics across your tracked universe</p></div><select aria-label="Time period"><option>Last 30 days</option><option>Last quarter</option></select></div>
      <div class="metrics">
        <article class="metric clickable" data-view="allcompanies"><div class="metric-top"><span class="metric-icon purple">▦</span></div><strong id="allCount">—</strong><p>All companies</p><small>Open database →</small></article>
        <article class="metric clickable" data-view="pefirms"><div class="metric-top"><span class="metric-icon purple">◈</span></div><strong id="peFirmCount">—</strong><p>Private equity firms</p><small>Open ownership portfolios →</small></article>
        <article class="metric clickable" data-view="vcfirms"><div class="metric-top"><span class="metric-icon green">◇</span></div><strong id="vcFirmCount">—</strong><p>VC companies</p><small>Open investment portfolios →</small></article>
        <article class="metric clickable" data-view="aicompanies"><div class="metric-top"><span class="metric-icon coral">⌁</span></div><strong id="aiCount">—</strong><p>AI companies</p><small>Open database →</small></article>
        <article class="metric clickable" data-view="datacenters"><div class="metric-top"><span class="metric-icon blue">⌖</span></div><strong id="dcCount">—</strong><p>Data center facilities</p><small>Open live database →</small></article>
        <article class="metric clickable" data-view="spacs"><div class="metric-top"><span class="metric-icon green">◎</span></div><strong id="spacCount">—</strong><p>Active SPACs</p><small>Open deadline monitor →</small></article>
      </div>

      <div class="dashboard-grid">
        <section class="panel activity-panel">
          <div class="panel-head"><div><h2>Recent activity</h2><p>Latest database updates</p></div><button id="refreshDashboard">Refresh data ↻</button></div>
          <div class="activity-list" id="activityRows"><div class="loading-row">Loading database activity…</div></div>
        </section>

        <section class="panel map-panel">
          <div class="panel-head"><div><h2>Data center footprint</h2><p>Capacity by region</p></div><button class="dots">•••</button></div>
          <div class="map" aria-label="Stylized map showing data center locations">
            <svg viewBox="0 0 650 310" role="img"><path d="M33 78l41-38 74-12 55 22 26 37-30 24-29 53-31 14-20-38-39-15-31-7zM166 186l38 20 23 53-25 44-25-44-16-43zM299 55l45-20 40 13 8 22 70-13 76 21 73 49-20 28-69 4-21 29-51-13-38 15-34-30-48-16-25-31-24-11zM382 181l45 4 34 38-4 64-47 13-31-56zM525 234l42-20 44 17 11 34-50 13-42-15z"/></svg>
            <div id="mapPins"></div>
          </div>
          <div class="region-row" id="regionRows"><div><span>No static capacity data</span><strong>Loading…</strong></div></div>
          <button class="full-map" data-view="datacenters">Explore interactive map <span>→</span></button>
        </section>
      </div>

      <div class="dashboard-grid lower">
        <section class="panel watch-panel"><div class="panel-head"><div><h2>Recently updated AI companies</h2><p>Live from the AI company database</p></div><button data-view="aicompanies">View all →</button></div><table><thead><tr><th>COMPANY</th><th>SECTOR</th><th>INVESTORS</th><th>VALUATION</th></tr></thead><tbody id="companyRows"></tbody></table></section>
        <section class="panel deadline-panel"><div class="panel-head"><div><h2>SPAC deadlines</h2><p>Live completion windows</p></div><button data-view="spacs">View all →</button></div><div id="spacRows"><div class="loading-row">Loading SPACs…</div></div></section>
      </div>
    </section>

    <section class="content module-view hidden" id="moduleView">
      <button class="back" data-view="overview">← Back to overview</button><p class="eyebrow" id="moduleEyebrow">OPPORTUNITY DATABASE</p><h1 id="moduleTitle">Companies</h1><p class="subtitle" id="moduleSubtitle"></p>
      <div class="module-stats" id="moduleStats"></div>
      <div class="module-toolbar"><label class="search-field"><span>⌕</span><input id="moduleSearch" placeholder="Search by name, sector, owner, or keyword…"></label><select id="moduleFilter"><option value="all">All categories</option></select><button class="outline" id="exportBtn">⇩ Export CSV</button><button class="primary" id="addRecord">＋ Add record</button></div>
      <div class="panel module-table"><div class="table-caption"><span id="resultCount">0 records</span><span>Click a row to open the research profile · Click a column to sort</span></div><div class="table-scroll"><table><thead id="moduleHead"></thead><tbody id="moduleBody"></tbody></table></div><div class="empty hidden" id="emptyState"><strong>No matching records</strong><span>Try removing a filter or broadening your search.</span></div></div>
    </section>
  </main>

  <dialog id="aiDialog"><button class="dialog-close" aria-label="Close">×</button><span class="dialog-spark">✦</span><h2>Research & update databases</h2><p>Ask AI to research records. Verified structured results will be written to the appropriate database.</p><form id="dialogForm"><textarea placeholder="Example: Research AI legal software companies and add their latest investors and valuations." required></textarea><label class="confirm-update"><input type="checkbox" required> I understand this will update the live database.</label><button class="primary">Research and update →</button></form><small>Every inserted record must include a source URL and is marked for review.</small><div class="source-badges"><span>Web search</span><span>PitchBook</span><span>Crunchbase</span><span>SEC / filings</span><span>Finance databases</span></div></dialog>
  <meta name="csrf-token" content="<?=htmlspecialchars(csrfToken())?>">
  <aside class="detail-drawer" id="detailDrawer" aria-hidden="true"><div class="drawer-head"><span id="drawerType">COMPANY PROFILE</span><button id="closeDrawer" aria-label="Close profile">×</button></div><div id="drawerContent"></div></aside><div class="drawer-backdrop" id="drawerBackdrop"></div>
  <div id="toast" role="status"></div>
  <script src="app.js?v=2"></script>
</body>
</html>
