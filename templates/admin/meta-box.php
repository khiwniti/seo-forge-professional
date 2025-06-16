<?php
/**
 * SEO Forge Meta Box Template
 * 
 * @package SEOForge
 * @since 2.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="seo-forge-meta-box">
    <table class="form-table">
        <tbody>
            <tr>
                <th scope="row">
                    <label for="seo_forge_meta_description">
                        <?php esc_html_e('Meta Description', 'seo-forge'); ?>
                    </label>
                </th>
                <td>
                    <textarea 
                        id="seo_forge_meta_description" 
                        name="seo_forge_meta_description" 
                        rows="3" 
                        cols="50" 
                        class="large-text"
                        maxlength="160"
                        placeholder="<?php esc_attr_e('Enter a compelling meta description (max 160 characters)', 'seo-forge'); ?>"
                    ><?php echo esc_textarea($meta_description); ?></textarea>
                    <p class="description">
                        <?php esc_html_e('A brief description of this page that will appear in search engine results.', 'seo-forge'); ?>
                        <span id="meta-description-counter">0/160</span>
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="seo_forge_focus_keyword">
                        <?php esc_html_e('Focus Keyword', 'seo-forge'); ?>
                    </label>
                </th>
                <td>
                    <input 
                        type="text" 
                        id="seo_forge_focus_keyword" 
                        name="seo_forge_focus_keyword" 
                        value="<?php echo esc_attr($focus_keyword); ?>" 
                        class="regular-text"
                        placeholder="<?php esc_attr_e('Enter your primary keyword', 'seo-forge'); ?>"
                    />
                    <p class="description">
                        <?php esc_html_e('The main keyword you want this page to rank for.', 'seo-forge'); ?>
                    </p>
                </td>
            </tr>
            
            <?php if ($seo_score): ?>
            <tr>
                <th scope="row">
                    <?php esc_html_e('SEO Score', 'seo-forge'); ?>
                </th>
                <td>
                    <div class="seo-score-display">
                        <div class="score-circle score-<?php echo esc_attr($this->getScoreClass($seo_score)); ?>">
                            <span class="score-number"><?php echo esc_html($seo_score); ?></span>
                        </div>
                        <div class="score-details">
                            <p class="score-text">
                                <?php echo esc_html($this->getScoreText($seo_score)); ?>
                            </p>
                            <button type="button" class="button button-secondary" id="analyze-content">
                                <?php esc_html_e('Re-analyze Content', 'seo-forge'); ?>
                            </button>
                        </div>
                    </div>
                </td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
    
    <div class="seo-forge-actions">
        <button type="button" class="button button-secondary" id="seo-forge-analyze">
            <?php esc_html_e('Analyze SEO', 'seo-forge'); ?>
        </button>
        <button type="button" class="button button-secondary" id="seo-forge-suggestions">
            <?php esc_html_e('Get Suggestions', 'seo-forge'); ?>
        </button>
    </div>
    
    <div id="seo-forge-analysis-results" class="analysis-results" style="display: none;">
        <!-- Analysis results will be populated here -->
    </div>
</div>

<style>
.seo-forge-meta-box .form-table th {
    width: 150px;
    padding: 15px 10px 15px 0;
}

.seo-forge-meta-box .form-table td {
    padding: 15px 10px;
}

#meta-description-counter {
    float: right;
    font-weight: bold;
}

.seo-score-display {
    display: flex;
    align-items: center;
    gap: 15px;
}

.score-circle {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    color: white;
}

.score-circle.score-good {
    background-color: #46b450;
}

.score-circle.score-ok {
    background-color: #ffb900;
}

.score-circle.score-bad {
    background-color: #dc3232;
}

.score-details {
    flex: 1;
}

.score-text {
    margin: 0 0 10px 0;
    font-weight: 500;
}

.seo-forge-actions {
    margin-top: 20px;
    padding-top: 15px;
    border-top: 1px solid #ddd;
}

.seo-forge-actions .button {
    margin-right: 10px;
}

.analysis-results {
    margin-top: 20px;
    padding: 15px;
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 4px;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Character counter for meta description
    $('#seo_forge_meta_description').on('input', function() {
        var length = $(this).val().length;
        var counter = $('#meta-description-counter');
        counter.text(length + '/160');
        
        if (length > 160) {
            counter.css('color', '#dc3232');
        } else if (length > 140) {
            counter.css('color', '#ffb900');
        } else {
            counter.css('color', '#46b450');
        }
    });
    
    // Trigger counter on page load
    $('#seo_forge_meta_description').trigger('input');
    
    // Analyze content button
    $('#seo-forge-analyze').on('click', function() {
        var button = $(this);
        var originalText = button.text();
        
        button.text('<?php esc_html_e('Analyzing...', 'seo-forge'); ?>').prop('disabled', true);
        
        // Get content from editor
        var content = '';
        if (typeof tinyMCE !== 'undefined' && tinyMCE.activeEditor) {
            content = tinyMCE.activeEditor.getContent();
        } else {
            content = $('#content').val();
        }
        
        var keyword = $('#seo_forge_focus_keyword').val();
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'seo_forge_analyze_content',
                content: content,
                keyword: keyword,
                _wpnonce: '<?php echo wp_create_nonce('seo_forge_meta_box'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    displayAnalysisResults(response.data);
                } else {
                    alert('<?php esc_html_e('Analysis failed. Please try again.', 'seo-forge'); ?>');
                }
            },
            error: function() {
                alert('<?php esc_html_e('An error occurred. Please try again.', 'seo-forge'); ?>');
            },
            complete: function() {
                button.text(originalText).prop('disabled', false);
            }
        });
    });
    
    function displayAnalysisResults(data) {
        var resultsDiv = $('#seo-forge-analysis-results');
        var html = '<h4><?php esc_html_e('SEO Analysis Results', 'seo-forge'); ?></h4>';
        
        html += '<div class="analysis-scores">';
        html += '<div class="score-item"><strong><?php esc_html_e('SEO Score:', 'seo-forge'); ?></strong> ' + data.seo_score + '/100</div>';
        html += '<div class="score-item"><strong><?php esc_html_e('Readability:', 'seo-forge'); ?></strong> ' + data.readability_score + '/100</div>';
        html += '<div class="score-item"><strong><?php esc_html_e('Keyword Density:', 'seo-forge'); ?></strong> ' + data.keyword_density + '%</div>';
        html += '</div>';
        
        if (data.suggestions && data.suggestions.length > 0) {
            html += '<h5><?php esc_html_e('Suggestions for Improvement:', 'seo-forge'); ?></h5>';
            html += '<ul>';
            data.suggestions.forEach(function(suggestion) {
                html += '<li>' + suggestion + '</li>';
            });
            html += '</ul>';
        }
        
        resultsDiv.html(html).show();
    }
});
</script>

<?php
// Helper methods for score display
if (!function_exists('getScoreClass')) {
    function getScoreClass($score) {
        if ($score >= 80) return 'good';
        if ($score >= 60) return 'ok';
        return 'bad';
    }
}

if (!function_exists('getScoreText')) {
    function getScoreText($score) {
        if ($score >= 80) return __('Excellent SEO optimization', 'seo-forge');
        if ($score >= 60) return __('Good SEO optimization', 'seo-forge');
        return __('Needs SEO improvement', 'seo-forge');
    }
}
?>