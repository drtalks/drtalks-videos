/**
 * DrTalks Videos — admin UI
 *
 * Handles: video search, expert search, Mode 2 batch import, Sync Now,
 * single refresh, TinyMCE button.
 */
/* global drtalksAdmin, tinymce */
(function ($) {
    'use strict';

    var ajaxUrl = (typeof drtalksAdmin !== 'undefined') ? drtalksAdmin.ajaxUrl : '';
    var nonce   = (typeof drtalksAdmin !== 'undefined') ? drtalksAdmin.nonce   : '';

    // --- Utility ---------------------------------------------------------------

    function debounce(fn, delay) {
        var timer;
        return function () {
            var args = arguments;
            var ctx  = this;
            clearTimeout(timer);
            timer = setTimeout(function () { fn.apply(ctx, args); }, delay);
        };
    }

    function ajax(action, data, cb) {
        data.action = action;
        data.nonce  = nonce;
        $.post(ajaxUrl, data, function (res) { cb(null, res); }).fail(function (xhr) {
            cb(xhr.statusText || 'Request failed');
        });
    }

    // --- Mode 1: Video search --------------------------------------------------

    var $videoSearch  = $('#drtalks-video-search');
    var $videoResults = $('#drtalks-video-results');
    var videoXHR      = null;

    if ($videoSearch.length) {
        $videoSearch.on('input', debounce(function () {
            var q = $(this).val().trim();
            if (videoXHR) { videoXHR.abort(); }

            $videoResults.html('<p>Searching…</p>').show();

            videoXHR = $.post(ajaxUrl, {
                action:   'drtalks_search_videos',
                nonce:    nonce,
                q:        q,
                per_page: 20,
            }, function (res) {
                videoXHR = null;
                if (!res.success) { $videoResults.html('<p>Error: ' + res.data + '</p>'); return; }
                renderVideoResults(res.data.videos || []);
            }).fail(function () {
                videoXHR = null;
                $videoResults.html('<p>Request failed.</p>');
            });
        }, 300));

        $videoResults.on('click', '.drtalks-result-item', function () {
            var $item = $(this);
            var slug = $item.data('slug');
            $item.css('opacity', '0.5').css('pointer-events', 'none');
            ajax('drtalks_import_video', { slug: slug }, function (err, res) {
                if (err || !res.success) {
                    $item.css('opacity', '').css('pointer-events', '');
                    alert('Import failed: ' + (err || (res && res.data) || 'Unknown error'));
                    return;
                }
                location.href = res.data.edit_url;
            });
        });
    }

    function renderVideoResults(videos) {
        if (!videos.length) { $videoResults.html('<p>No videos found.</p>'); return; }
        var html = '';
        $.each(videos, function (i, v) {
            html += '<div class="drtalks-result-item" data-slug="' + escAttr(v.slug) + '">';
            if (v.thumbnail_url) {
                html += '<img src="' + escAttr(v.thumbnail_url) + '" alt="" />';
            }
            html += '<span>' + escHtml(v.title) + '</span></div>';
        });
        $videoResults.html(html).show();
    }

    // --- Mode 2: Expert search -------------------------------------------------

    var $expertSearch    = $('#drtalks-expert-search');
    var $expertResults   = $('#drtalks-expert-results');
    var $expertSelected  = $('#drtalks-expert-selected');
    var $expertNameSpan  = $('#drtalks-expert-name');
    var $expertSlugInput = $('#drtalks-expert-slug-hidden');
    var expertXHR        = null;

    if ($expertSearch.length) {
        $expertSearch.on('input', debounce(function () {
            var q = $(this).val().trim();
            if (expertXHR) { expertXHR.abort(); }

            $expertResults.html('<p>Searching…</p>').show();

            expertXHR = $.post(ajaxUrl, {
                action:   'drtalks_search_experts',
                nonce:    nonce,
                q:        q,
                per_page: 20,
            }, function (res) {
                expertXHR = null;
                if (!res.success) { $expertResults.html('<p>Error: ' + res.data + '</p>'); return; }
                renderExpertResults(res.data.experts || []);
            }).fail(function () {
                expertXHR = null;
                $expertResults.html('<p>Request failed.</p>');
            });
        }, 300));

        $expertResults.on('click', '.drtalks-result-item', function () {
            var slug = $(this).data('slug');
            var name = $(this).data('name');
            $expertSlugInput.val(slug);
            $expertNameSpan.text(name);
            $expertSelected.show();
            $expertResults.hide();
        });
    }

    function renderExpertResults(experts) {
        if (!experts.length) { $expertResults.html('<p>No experts found.</p>'); return; }
        var html = '';
        $.each(experts, function (i, e) {
            html += '<div class="drtalks-result-item" data-slug="' + escAttr(e.slug) + '" data-name="' + escAttr(e.name) + '">';
            if (e.photo_url) {
                html += '<img src="' + escAttr(e.photo_url) + '" alt="" />';
            }
            html += '<span>' + escHtml(e.name) + '</span></div>';
        });
        $expertResults.html(html).show();
    }

    // --- Mode 2: Batch import --------------------------------------------------

    var $loadAllBtn    = $('#drtalks-load-all-videos');
    var $progressWrap  = $('#drtalks-import-progress');
    var $progressFill  = $('.drtalks-progress-fill');
    var $importStatus  = $('#drtalks-import-status');

    if ($loadAllBtn.length) {
        $loadAllBtn.on('click', function () {
            var expertSlug = $expertSlugInput.val();
            if (!expertSlug) { return; }

            $progressWrap.show();
            $loadAllBtn.prop('disabled', true);
            importPage(expertSlug, 1, 0, 0);
        });
    }

    function importPage(expertSlug, page, totalCreated, totalSkipped) {
        ajax('drtalks_load_expert_videos', { expert_slug: expertSlug, page: page, per_page: 100 }, function (err, res) {
            if (err || !res.success) {
                $importStatus.text('Error: ' + (err || res.data));
                $loadAllBtn.prop('disabled', false);
                return;
            }

            var data     = res.data;
            var videos   = data.videos || [];
            var total    = data.total || videos.length;
            var hasMore  = !!data.has_more;
            var imported = 0;

            function importNext(idx) {
                if (idx >= videos.length) {
                    var done = (page - 1) * 100 + videos.length;
                    var pct  = total > 0 ? Math.round(done / total * 100) : 100;
                    $progressFill.css('width', pct + '%');
                    $importStatus.text('Imported ' + (totalCreated + imported) + ' videos so far…');

                    if (hasMore) {
                        importPage(expertSlug, page + 1, totalCreated + imported, totalSkipped);
                    } else {
                        $importStatus.text('Done! ' + (totalCreated + imported) + ' videos imported.');
                        $loadAllBtn.prop('disabled', false);
                    }
                    return;
                }

                var slug = videos[idx].slug;
                ajax('drtalks_import_video', { slug: slug }, function (e, r) {
                    if (!e && r.success) { imported++; }
                    importNext(idx + 1);
                });
            }

            importNext(0);
        });
    }

    // --- Settings page: Sync Now -----------------------------------------------

    var $syncNowBtn    = $('#drtalks-sync-now');
    var $syncStatus    = $('#drtalks-sync-status');

    if ($syncNowBtn.length) {
        $syncNowBtn.on('click', function () {
            var expertSlug = $(this).data('expert');
            if (!expertSlug) { return; }

            $syncStatus.text('Syncing…').show();
            $syncNowBtn.prop('disabled', true);

            importPage(expertSlug, 1, 0, 0);
        });
    }

    // --- Single refresh --------------------------------------------------------

    var $refreshSingle = $('#drtalks-refresh-single');

    if ($refreshSingle.length) {
        $refreshSingle.on('click', function () {
            var slug = $(this).data('slug');
            $(this).text('Refreshing…').prop('disabled', true);
            ajax('drtalks_import_video', { slug: slug }, function (err, res) {
                if (err || !res.success) {
                    alert('Refresh failed: ' + (err || res.data));
                } else {
                    location.reload();
                }
            });
        });
    }

    // --- TinyMCE plugin --------------------------------------------------------

    if (typeof tinymce !== 'undefined') {
        tinymce.PluginManager.add('drtalks_video_button', function (editor) {
            editor.addButton('drtalks_video_button', {
                text:    'DrTalks Video',
                tooltip: 'Insert DrTalks Video',
                onclick: function () { openTinyMCEModal(editor); },
            });
        });
    }

    function openTinyMCEModal(editor) {
        var optionsHtml =
            '<div style="margin:12px 0;padding:12px;background:#f9f9f9;border:1px solid #ddd;border-radius:3px;">' +
            '<strong style="display:block;margin-bottom:8px;font-size:12px;text-transform:uppercase;color:#555;">Display Options</strong>' +
            '<label style="display:inline-flex;align-items:center;gap:5px;margin-right:14px;font-size:13px;cursor:pointer;"><input type="checkbox" id="drtalks-mce-show-title"       checked> Title</label>' +
            '<label style="display:inline-flex;align-items:center;gap:5px;margin-right:14px;font-size:13px;cursor:pointer;"><input type="checkbox" id="drtalks-mce-show-description" checked> Description</label>' +
            '<label style="display:inline-flex;align-items:center;gap:5px;margin-right:14px;font-size:13px;cursor:pointer;"><input type="checkbox" id="drtalks-mce-show-transcript"  checked> Transcript</label>' +
            '<label style="display:inline-flex;align-items:center;gap:5px;font-size:13px;cursor:pointer;"><input type="checkbox" id="drtalks-mce-show-author"      checked> About Author</label>' +
            '</div>';

        var $modal = $('<div id="drtalks-tinymce-modal" style="position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.6);z-index:100000;display:flex;align-items:center;justify-content:center;">' +
            '<div style="background:#fff;padding:24px;width:520px;max-width:95%;border-radius:4px;box-shadow:0 4px 24px rgba(0,0,0,.2);">' +
            '<h3 style="margin-top:0;">Insert DrTalks Video</h3>' +
            '<input type="text" id="drtalks-mce-search" placeholder="Search by title or expert name…" style="width:100%;box-sizing:border-box;margin-bottom:4px;" />' +
            '<div id="drtalks-mce-status" style="font-size:12px;color:#888;min-height:18px;margin-bottom:4px;"></div>' +
            '<div id="drtalks-mce-results" style="max-height:260px;overflow-y:auto;border:1px solid #ddd;border-radius:3px;display:none;"></div>' +
            optionsHtml +
            '<p style="margin:0;"><button type="button" id="drtalks-mce-close" class="button">Cancel</button></p>' +
            '</div></div>');

        $('body').append($modal);
        $modal.find('#drtalks-mce-close').on('click', function () { $modal.remove(); });
        $modal.find('#drtalks-mce-search').trigger('focus');

        $modal.find('#drtalks-mce-search').on('input', debounce(function () {
            var q = $(this).val().trim();
            if ( q.length < 2 ) {
                $modal.find('#drtalks-mce-results').hide().html('');
                $modal.find('#drtalks-mce-status').text('');
                return;
            }
            $modal.find('#drtalks-mce-status').text('Searching…');
            $modal.find('#drtalks-mce-results').hide();

            ajax('drtalks_search_videos', { q: q, per_page: 20 }, function (err, res) {
                $modal.find('#drtalks-mce-status').text('');
                if (err || !res.success) {
                    $modal.find('#drtalks-mce-results').html('<p style="padding:8px;margin:0;">Error searching.</p>').show();
                    return;
                }
                var videos = res.data.videos || [];
                if (!videos.length) {
                    $modal.find('#drtalks-mce-results').html('<p style="padding:8px;margin:0;">No videos found.</p>').show();
                    return;
                }
                var html = '';
                $.each(videos, function (i, v) {
                    html += '<div class="drtalks-mce-item" data-slug="' + escAttr(v.slug) + '" ' +
                        'style="padding:8px 10px;cursor:pointer;border-bottom:1px solid #f0f0f0;display:flex;gap:10px;align-items:center;">' +
                        (v.thumbnail_url ? '<img src="' + escAttr(v.thumbnail_url) + '" style="width:56px;height:40px;object-fit:cover;flex-shrink:0;border-radius:2px;" />' : '') +
                        '<span style="font-size:13px;">' + escHtml(v.title) + '</span>' +
                        '</div>';
                });
                $modal.find('#drtalks-mce-results').html(html).show();
            });
        }, 300));

        $modal.on('mouseenter', '.drtalks-mce-item', function () { $(this).css('background', '#f5f5f5'); });
        $modal.on('mouseleave', '.drtalks-mce-item', function () { $(this).css('background', ''); });

        $modal.on('click', '.drtalks-mce-item', function () {
            var slug  = $(this).data('slug');
            var title = $modal.find('#drtalks-mce-show-title').is(':checked')       ? '' : ' title="0"';
            var desc  = $modal.find('#drtalks-mce-show-description').is(':checked') ? '' : ' description="0"';
            var trans = $modal.find('#drtalks-mce-show-transcript').is(':checked')  ? '' : ' transcript="0"';
            var auth  = $modal.find('#drtalks-mce-show-author').is(':checked')      ? '' : ' author="0"';
            var shortcode = '[drtalks_video slug="' + slug + '"' + title + desc + trans + auth + ']';
            editor.execCommand('mceInsertContent', false, shortcode);
            $modal.remove();
        });
    }

    // --- Helpers ---------------------------------------------------------------

    function escHtml(str) {
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function escAttr(str) {
        return escHtml(str);
    }

}(jQuery));
