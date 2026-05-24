/**
 * Rank AI Schema — Admin JS v2.0
 * Handles: SEO gauge chart, distribution chart, run-all, per-page analyzer,
 *          table filter/search, meta box tabs, schema group toggling, char counter.
 */
/* global RAS, RAS_SEO, Chart */
(function ($) {
    'use strict';

    /* ═══ SEO ANALYSIS PAGE ══════════════════════════════════════════ */
    if (typeof RAS_SEO !== 'undefined') {
        initGauge();
        initDist();
        initRunAll();
        initPageAnalyzer();
        initTable();
    }

    /* ── Gauge Chart ──────────────────────────────────────────────── */
    function initGauge() {
        var ctx = document.getElementById('ras-gauge');
        if (!ctx) { return; }
        var avg   = RAS_SEO.summary.avg || 0;
        var color = scoreColor(avg);
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                datasets: [{
                    data: [avg, 100 - avg],
                    backgroundColor: [color, '#1a2840'],
                    borderWidth: 0,
                    circumference: 270,
                    rotation: 225,
                }]
            },
            options: {
                cutout: '78%',
                plugins: { legend: { display: false }, tooltip: { enabled: false } },
                animation: { duration: 800 }
            }
        });
    }

    /* ── Distribution Donut ───────────────────────────────────────── */
    function initDist() {
        var ctx = document.getElementById('ras-dist');
        if (!ctx) { return; }
        var s = RAS_SEO.summary;
        var total = s.excellent + s.good + s.needs_work + s.poor;
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Excellent', 'Good', 'Needs Work', 'Poor'],
                datasets: [{
                    data: [s.excellent, s.good, s.needs_work, s.poor],
                    backgroundColor: ['#10b981', '#3b82f6', '#f59e0b', '#ef4444'],
                    borderColor: '#0d1117',
                    borderWidth: 2,
                }]
            },
            options: {
                cutout: '65%',
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: function(c) { return ' ' + c.label + ': ' + c.raw; } } }
                },
                animation: { duration: 600 }
            }
        });
    }

    /* ── Run All Analysis ─────────────────────────────────────────── */
    function initRunAll() {
        $('#ras-run-btn').on('click', function () {
            var $btn = $(this).prop('disabled', true).text('Analyzing…');
            $('#ras-prog-wrap').show();
            animateProgress();

            $.post(RAS.ajax, { action: 'ras_analyze_all', nonce: RAS.nonce }, function (res) {
                if (!res.success) { alert('Error. Please try again.'); $btn.prop('disabled', false).text('▶ Analyze Entire Site'); return; }

                var s = res.data.summary;
                $('#ras-stat-total').text(s.total);
                $('#ras-stat-analyzed').text(s.total);
                $('#ras-stat-pending').text(0);
                $('#ras-last-run').text('just now');
                $('#ras-prog-fill').css('width', '100%');
                $('#ras-prog-txt').text('✓ Analysis complete — ' + s.total + ' pages analyzed');
                $btn.prop('disabled', false).text('▶ Analyze Entire Site');

                // Update table rows
                updateTableRows(res.data.posts);

                // Reload the page select options
                updatePageSelect(res.data.posts);
            }).fail(function () {
                $btn.prop('disabled', false).text('▶ Analyze Entire Site');
                $('#ras-prog-txt').text('Error. Please try again.');
            });
        });
    }

    function animateProgress() {
        var pct = 0;
        var iv = setInterval(function () {
            pct = Math.min(pct + Math.random() * 12, 90);
            $('#ras-prog-fill').css('width', pct + '%');
            if (pct >= 90) { clearInterval(iv); }
        }, 300);
    }

    function updateTableRows(posts) {
        posts.forEach(function (p) {
            var $row = $('[data-id="' + p.id + '"]');
            if (!$row.length) { return; }
            $row.attr('data-label', p.label);
            var scoreHtml = p.score !== null
                ? '<div class="ras-mini-bar-wrap"><div class="ras-mini-bar"><div class="ras-mini-fill ras-fill-' + p.label + '" style="width:' + p.score + '%"></div></div><strong>' + p.score + '</strong></div>'
                : '<span class="ras-muted-dash">—</span>';
            $row.find('td:nth-child(3)').html(scoreHtml);

            var labels = { excellent: '★ Excellent', good: '▲ Good', needs_work: '⚠ Needs Work', poor: '✗ Poor', unanalyzed: '○ Not Analyzed' };
            $row.find('td:nth-child(4)').html('<span class="ras-status-badge ras-sb-' + p.label + '">' + (labels[p.label] || p.label) + '</span>');
            $row.find('.ras-ts-cell').text('just now');
        });
    }

    function updatePageSelect(posts) {
        var $sel = $('#ras-page-select');
        var current = $sel.val();
        $sel.find('option[value!=""]').each(function () {
            var id = $(this).val();
            var p = posts.find(function (x) { return String(x.id) === String(id); });
            if (p && p.score !== null) {
                $(this).text(p.title + ' (' + p.score + '/100)')
                    .attr('data-score', p.score).attr('data-label', p.label);
            }
        });
        if (current) { $sel.val(current); }
    }

    /* ── Per-Page Analyzer ─────────────────────────────────────────── */
    function initPageAnalyzer() {
        $('#ras-analyze-btn').on('click', function () {
            var postId = $('#ras-page-select').val();
            if (!postId) { alert('Please select a page.'); return; }
            runPageAnalysis(postId);
        });

        // Clicking a row in the table
        $(document).on('click', '.ras-open-row', function (e) {
            e.preventDefault();
            var $el = $(this);
            var id = $el.data('id');
            $('#ras-page-select').val(id);
            $('#ras-edit-link').attr('href', $el.data('edit') || '#').show();
            $('#ras-view-link').attr('href', $el.data('view') || '#').show();
            runPageAnalysis(id);
            $('html,body').animate({ scrollTop: $('#ras-page-select').offset().top - 80 }, 300);
        });

        $('#ras-page-select').on('change', function () {
            var $opt = $(this).find('option:selected');
            var edit = $opt.data('edit');
            var view = $opt.data('view');
            if (edit) { $('#ras-edit-link').attr('href', edit).show(); } else { $('#ras-edit-link').hide(); }
            if (view) { $('#ras-view-link').attr('href', view).show(); } else { $('#ras-view-link').hide(); }
        });
    }

    function runPageAnalysis(postId) {
        $('#ras-results').hide();
        $('#ras-empty-hint').hide();
        $('#ras-analyzing').show();

        $.post(RAS.ajax, { action: 'ras_analyze_post', nonce: RAS.nonce, post_id: postId }, function (res) {
            $('#ras-analyzing').hide();
            if (!res.success) { alert('Analysis failed.'); return; }
            renderResults(res.data, postId);
        }).fail(function () {
            $('#ras-analyzing').hide();
            alert('Connection error.');
        });
    }

    function renderResults(data, postId) {
        var score  = data.score;
        var label  = data.label;
        var checks = data.results;

        // Update the table row score live
        var $row = $('[data-id="' + postId + '"]');
        if ($row.length) {
            $row.attr('data-label', label);
        }

        // Score header
        var $circle = $('#ras-score-circle').removeClass('excellent good needs_work poor').addClass(label);
        $('#ras-score-num').text(score);
        $('#ras-cnt-pass').text(data.pass);
        $('#ras-cnt-warn').text(data.warn);
        $('#ras-cnt-fail').text(data.fail);

        var $fill = $('#ras-score-fill').removeClass('excellent good needs_work poor').addClass(label);
        $fill.css('width', score + '%');
        var $badge = $('#ras-score-badge').removeClass('excellent good needs_work poor').addClass(label);
        var labelText = { excellent: '★ Excellent', good: '▲ Good', needs_work: '⚠ Needs Work', poor: '✗ Poor' };
        $badge.text(labelText[label] || label);

        // Page title
        var $opt = $('#ras-page-select option[value="' + postId + '"]');
        $('#ras-result-title').text($opt.text().replace(/\s*\(.*\)$/, ''));

        // Group checks by category
        var grouped = {};
        var catOrder = ['content', 'meta', 'schema', 'social', 'keyword'];
        catOrder.forEach(function (cat) { grouped[cat] = []; });
        Object.keys(checks).forEach(function (id) {
            var r = checks[id];
            if (!grouped[r.cat]) { grouped[r.cat] = []; }
            grouped[r.cat].push(r);
        });

        var icons = { pass: '✓', warn: '⚠', fail: '✗' };
        var catNames = RAS_SEO.cats;
        var catIcons = RAS_SEO.catIcons;
        var html = '';

        catOrder.forEach(function (cat) {
            var items = grouped[cat];
            if (!items || !items.length) { return; }
            var catPass = items.filter(function (r) { return r.status === 'pass'; }).length;
            html += '<div class="ras-check-cat">';
            html += '<div class="ras-check-cat-head"><h5>' + (catIcons[cat] || '') + ' ' + (catNames[cat] || cat) + '</h5>';
            html += '<span class="ras-check-cat-score">' + catPass + '/' + items.length + '</span></div>';
            items.forEach(function (r) {
                html += '<div class="ras-check-item ' + r.status + '">';
                html += '<span class="ras-ci-icon">' + icons[r.status] + '</span>';
                html += '<div class="ras-ci-body">';
                html += '<div class="ras-ci-label">' + escHtml(r.label) + '</div>';
                html += '<div class="ras-ci-msg">' + escHtml(r.message) + '</div>';
                if (r.fix) { html += '<div class="ras-ci-fix">💡 ' + escHtml(r.fix) + '</div>'; }
                html += '</div></div>';
            });
            html += '</div>';
        });

        $('#ras-check-grid').html(html);
        $('#ras-results').show();
    }

    /* ── Table Filter + Search ────────────────────────────────────── */
    function initTable() {
        var $rows   = $('#ras-pages-table .ras-tr');
        var current = 'all';
        var query   = '';

        function applyFilters() {
            $rows.each(function () {
                var $r   = $(this);
                var lbl  = $r.data('label') || 'unanalyzed';
                var txt  = $r.text().toLowerCase();
                var show = (current === 'all' || lbl === current) && (!query || txt.includes(query));
                $r.toggleClass('ras-hidden', !show);
            });
        }

        $('#ras-filters').on('click', '.ras-filter-btn', function () {
            current = $(this).data('filter');
            $(this).addClass('active').siblings().removeClass('active');
            applyFilters();
        });

        $('#ras-table-search').on('input', function () {
            query = $(this).val().toLowerCase().trim();
            applyFilters();
        });
    }

    /* ═══ META BOX ═══════════════════════════════════════════════════ */
    if ($('.ras-mb').length) {
        initMetaBoxTabs();
        initSchemaGroups();
        initRepeaters();
        initMetaDescCounter();
        initJsonValidator();
        initMbAnalyze();
    }

    /* ── Meta Box Tabs ───────────────────────────────────────────── */
    function initMetaBoxTabs() {
        $(document).on('click', '.ras-mb-tab', function () {
            var tab = $(this).data('tab');
            $('.ras-mb-tab').removeClass('ras-mb-tab-active');
            $(this).addClass('ras-mb-tab-active');
            $('.ras-mb-panel').removeClass('ras-mb-panel-active');
            $('#ras-tab-' + tab).addClass('ras-mb-panel-active');
        });
    }

    /* ── Schema mode → show/hide override section ─────────────────── */
    $(document).on('change', 'input[name="_ras_schema_mode"]', function () {
        $('#ras-schema-override').toggle(this.value === 'override');
    });

    /* ── Schema type → show/hide groups ──────────────────────────── */
    function initSchemaGroups() {
        function update() {
            var val = $('#ras_schema_type').val() || '';
            $('.ras-schema-group').each(function () {
                var forTypes = $(this).data('for') || '';
                $(this).toggleClass('ras-sg-active', forTypes.indexOf(val) !== -1);
            });
        }
        $('#ras_schema_type').on('change', update);
        update();
    }

    /* ── Repeater rows (FAQ + Steps) ─────────────────────────────── */
    function initRepeaters() {
        $(document).on('click', '.ras-add-row', function () {
            var listId = $(this).data('list');
            var tpl    = $(this).data('template');
            var $list  = $('#' + listId);
            var num    = $list.children().length + 1;
            var html;
            if (tpl === 'faq') {
                html = '<div class="ras-repeater-item"><div class="ras-ri-num">' + num + '</div><div class="ras-ri-body"><input type="text" name="_ras_faq_q[]" placeholder="Question"><textarea name="_ras_faq_a[]" rows="2" placeholder="Answer"></textarea></div><button type="button" class="ras-ri-del" data-list="' + listId + '">✕</button></div>';
            } else {
                html = '<div class="ras-repeater-item"><div class="ras-ri-num">' + num + '</div><div class="ras-ri-body"><input type="text" name="_ras_step_name[]" placeholder="Step title"><textarea name="_ras_step_text[]" rows="2" placeholder="Step description"></textarea></div><button type="button" class="ras-ri-del" data-list="' + listId + '">✕</button></div>';
            }
            $list.append(html);
            renumberList($list);
        });

        $(document).on('click', '.ras-ri-del', function () {
            var $list = $('#' + $(this).data('list'));
            $(this).closest('.ras-repeater-item').remove();
            renumberList($list);
        });
    }

    function renumberList($list) {
        $list.children().each(function (i) {
            $(this).find('.ras-ri-num').text(i + 1);
        });
    }

    /* ── Meta desc character counter ──────────────────────────────── */
    function initMetaDescCounter() {
        var $ta    = $('#ras-meta-desc');
        var $count = $('#ras-desc-count');
        var $fill  = $('#ras-desc-fill');

        function update() {
            var len = $ta.val().length;
            $count.text(len + ' / 160');
            var pct = Math.min(len / 160 * 100, 100);
            var bg  = len < 120 ? '#f59e0b' : (len <= 160 ? '#10b981' : '#ef4444');
            $fill.css({ width: pct + '%', background: bg });
        }

        $ta.on('input', update);
        update();
    }

    /* ── JSON Validator ───────────────────────────────────────────── */
    function initJsonValidator() {
        $(document).on('input', '.ras-code-area', function () {
            var val = $(this).val().trim();
            var $st = $('#ras-json-status');
            if (!val) { $st.hide().removeClass('ok err'); return; }
            try {
                JSON.parse(val);
                $st.removeClass('err').addClass('ok').text('✓ Valid JSON-LD').show();
            } catch (e) {
                $st.removeClass('ok').addClass('err').text('✗ ' + e.message).show();
            }
        });
    }

    /* ── Meta box "Analyze Now" button ───────────────────────────── */
    function initMbAnalyze() {
        $(document).on('click', '.ras-mb-analyze', function () {
            var $btn = $(this).prop('disabled', true).text('Analyzing…');
            var postId = $(this).data('post');

            $.post(RAS.ajax, { action: 'ras_analyze_post', nonce: RAS.nonce, post_id: postId }, function (res) {
                $btn.prop('disabled', false);
                if (!res.success) { $btn.text('✗ Error'); return; }
                var d = res.data;
                $btn.text('↺ Re-analyze');

                // Update score circle
                var $circle = $('.ras-seo-circle').removeClass('ras-seo-circle-excellent ras-seo-circle-good ras-seo-circle-needs_work ras-seo-circle-poor ras-seo-circle-none')
                    .addClass('ras-seo-circle-' + d.label);
                $circle.find('span').text(d.score);
                $('.ras-seo-score-meta p').text('Just analyzed');

                // Update badge in tab
                var $badge = $('.ras-mb-badge').removeClass('ras-mb-badge-excellent ras-mb-badge-good ras-mb-badge-needs_work ras-mb-badge-poor')
                    .addClass('ras-mb-badge-' + d.label).text(d.score).show();

                // Render mini checks
                var icons = { pass: '✓', warn: '⚠', fail: '✗' };
                var html = '';
                Object.keys(d.results).forEach(function (id) {
                    var r = d.results[id];
                    html += '<div class="ras-mc ' + r.status + '">';
                    html += '<span class="ras-mc-icon">' + icons[r.status] + '</span>';
                    html += '<span class="ras-mc-label">' + escHtml(r.label) + '</span>';
                    html += '<span class="ras-mc-msg">' + escHtml(r.message) + '</span>';
                    html += '</div>';
                });
                $('#ras-mb-checks').html(html);
            }).fail(function () {
                $btn.prop('disabled', false).text('✗ Error');
            });
        });
    }

    /* ── Utility ─────────────────────────────────────────────────── */
    function scoreColor(s) {
        if (s >= 80) { return '#10b981'; }
        if (s >= 60) { return '#3b82f6'; }
        if (s >= 40) { return '#f59e0b'; }
        return '#ef4444';
    }

    function escHtml(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

})(jQuery);
