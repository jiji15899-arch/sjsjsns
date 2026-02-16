<?php
/**
 * Plugin Name: 아백 홈페이지형 글 생성기
 * Plugin URI: https://aros100.com
 * Description: Gemini API를 이용하여 워드프레스 글쓰기 화면에서 홈페이지형 글을 자동 생성합니다.
 * Version: 1.0.0
 * Author: 아로스
 * Author URI: https://aros100.com
 * License: GPL v2 or later
 * Text Domain: abaek-homepage-generator
 */

if (!defined('ABSPATH')) {
    exit;
}

class AbaekHomepageGenerator {
    private $option_name = 'ahg_settings';
    private $current_api_key_index_option = 'ahg_current_key_index';
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('add_meta_boxes', array($this, 'add_meta_box'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_ahg_generate_content', array($this, 'ajax_generate_content'));
    }
    
    public function add_admin_menu() {
        add_options_page(
            '아백 홈페이지형 글 생성기 설정',
            '홈페이지형 글 생성기',
            'manage_options',
            'abaek-homepage-generator',
            array($this, 'render_settings_page')
        );
    }
    
    public function register_settings() {
        register_setting($this->option_name, $this->option_name);
    }
    
    public function render_settings_page() {
        $settings = get_option($this->option_name, array());
        ?>
        <div class="wrap">
            <h1>🏠 아백 홈페이지형 글 생성기 설정</h1>
            
            <div style="background: #fff; padding: 20px; margin: 20px 0; border-left: 4px solid #2271b1;">
                <h2>📌 사용 방법</h2>
                <ol>
                    <li>아래에 <strong>Gemini API 키</strong>를 최대 5개까지 입력하세요</li>
                    <li>글쓰기 화면에서 <strong>"홈페이지형 글 생성"</strong> 메타박스를 확인하세요</li>
                    <li>키워드와 탭을 입력하고 <strong>"생성하기"</strong> 버튼을 클릭하세요</li>
                    <li>생성된 HTML 코드를 에디터에 복사하여 사용하세요</li>
                </ol>
                <p><strong>💡 팁:</strong> API 키 하나가 할당량을 초과하면 자동으로 다음 키로 전환됩니다!</p>
            </div>
            
            <form method="post" action="options.php">
                <?php settings_fields($this->option_name); ?>
                
                <table class="form-table">
                    <tr>
                        <th colspan="2">
                            <h2>🔑 Gemini API 키 설정 (최대 5개)</h2>
                            <p>
                                <a href="https://makersuite.google.com/app/apikey" target="_blank">
                                    👉 Gemini API 키 발급받기
                                </a>
                            </p>
                        </th>
                    </tr>
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                    <tr>
                        <th scope="row">
                            <label for="api_key_<?php echo $i; ?>">
                                API 키 #<?php echo $i; ?>
                                <?php if ($i === 1): ?>
                                    <span style="color: red;">*</span>
                                <?php endif; ?>
                            </label>
                        </th>
                        <td>
                            <input 
                                type="text" 
                                id="api_key_<?php echo $i; ?>"
                                name="<?php echo $this->option_name; ?>[api_key_<?php echo $i; ?>]"
                                value="<?php echo esc_attr(isset($settings['api_key_' . $i]) ? $settings['api_key_' . $i] : ''); ?>"
                                class="regular-text"
                                placeholder="AIza..."
                                <?php echo ($i === 1) ? 'required' : ''; ?>
                            />
                        </td>
                    </tr>
                    <?php endfor; ?>
                </table>
                
                <?php submit_button('설정 저장'); ?>
            </form>
            
            <div style="background: #f0f0f1; padding: 15px; margin-top: 30px; border-radius: 5px;">
                <h3>📊 API 키 상태</h3>
                <p>현재 사용 중인 API 키: <strong>#<?php echo get_option($this->current_api_key_index_option, 1); ?></strong></p>
                <form method="post" action="">
                    <input type="hidden" name="reset_api_rotation" value="1">
                    <?php wp_nonce_field('reset_rotation', 'reset_nonce'); ?>
                    <button type="submit" class="button button-secondary">🔄 API 키 순번 초기화</button>
                </form>
            </div>
        </div>
        <?php
        
        // API 키 순번 초기화 처리
        if (isset($_POST['reset_api_rotation']) && check_admin_referer('reset_rotation', 'reset_nonce')) {
            update_option($this->current_api_key_index_option, 1);
            echo '<div class="notice notice-success"><p>✅ API 키 순번이 초기화되었습니다.</p></div>';
        }
    }
    
    public function add_meta_box() {
        add_meta_box(
            'ahg_generator',
            '🏠 홈페이지형 글 생성기',
            array($this, 'render_meta_box'),
            'post',
            'normal',
            'high'
        );
    }
    
    public function render_meta_box($post) {
        ?>
        <div id="ahg-generator-container">
            <style>
                #ahg-generator-container { padding: 15px; }
                .ahg-input-group { margin-bottom: 15px; }
                .ahg-input-group label { display: block; font-weight: 600; margin-bottom: 5px; color: #1d2327; }
                .ahg-input-group input[type="text"],
                .ahg-input-group textarea { width: 100%; padding: 8px; border: 1px solid #8c8f94; border-radius: 4px; }
                .ahg-tab-row { display: flex; gap: 10px; margin-bottom: 8px; }
                .ahg-tab-row input { flex: 1; }
                .ahg-button { background: #2271b1; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; font-weight: 600; margin-right: 10px; }
                .ahg-button:hover { background: #135e96; }
                .ahg-button:disabled { background: #8c8f94; cursor: not-allowed; }
                .ahg-result { margin-top: 20px; padding: 15px; background: #f6f7f7; border: 1px solid #c3c4c7; border-radius: 4px; }
                .ahg-loading { display: inline-block; margin-left: 10px; }
                .ahg-error { background: #fcf0f1; border-color: #cc1818; color: #cc1818; padding: 10px; border-radius: 4px; margin-top: 10px; }
                .ahg-success { background: #ecf7ed; border-color: #2c6e49; color: #2c6e49; padding: 10px; border-radius: 4px; margin-top: 10px; }
                .ahg-code-block { background: #282c34; color: #abb2bf; padding: 15px; border-radius: 4px; overflow-x: auto; font-family: monospace; font-size: 12px; max-height: 400px; }
                .ahg-version-tabs { display: flex; gap: 10px; margin-bottom: 15px; }
                .ahg-version-tab { padding: 10px 20px; border: 1px solid #8c8f94; background: white; cursor: pointer; border-radius: 4px; }
                .ahg-version-tab.active { background: #2271b1; color: white; border-color: #2271b1; }
            </style>
            
            <div class="ahg-input-group">
                <label>키워드 <span style="color: red;">*</span></label>
                <input type="text" id="ahg-keyword" placeholder="예: 근로장려금, 청년도약계좌, 제주도여행 등" />
            </div>
            
            <div class="ahg-input-group">
                <label>탭 메뉴 (최대 4개) <span style="color: red;">*</span></label>
                <?php for ($i = 1; $i <= 4; $i++): ?>
                <div class="ahg-tab-row">
                    <input type="text" id="ahg-tab-<?php echo $i; ?>" placeholder="탭 <?php echo $i; ?> 이름" />
                    <input type="url" id="ahg-tab-link-<?php echo $i; ?>" placeholder="탭 <?php echo $i; ?> 링크 (선택)" />
                </div>
                <?php endfor; ?>
            </div>
            
            <div class="ahg-input-group">
                <label>애드센스 광고 코드 (선택사항)</label>
                <textarea id="ahg-adsense" rows="4" placeholder="애드센스 광고 코드를 입력하세요"></textarea>
            </div>
            
            <div class="ahg-input-group">
                <label>버전 2 버튼 링크 URL</label>
                <input type="url" id="ahg-version2-url" placeholder="https://example.com" />
            </div>
            
            <div>
                <button type="button" class="ahg-button" id="ahg-generate-v2">
                    📋 버전 2 생성 (신청·절차 중심)
                </button>
                <button type="button" class="ahg-button" id="ahg-generate-v1" style="display: none;">
                    💰 버전 1 생성 (혜택·조건 중심)
                </button>
                <span id="ahg-loading" class="ahg-loading" style="display: none;">
                    <span class="spinner is-active"></span> 생성 중...
                </span>
            </div>
            
            <div id="ahg-error" class="ahg-error" style="display: none;"></div>
            <div id="ahg-success" class="ahg-success" style="display: none;"></div>
            
            <div id="ahg-result" class="ahg-result" style="display: none;">
                <div class="ahg-version-tabs">
                    <button type="button" class="ahg-version-tab" data-version="2">버전 2 (신청·절차)</button>
                    <button type="button" class="ahg-version-tab" data-version="1">버전 1 (혜택·조건)</button>
                </div>
                <button type="button" class="button button-secondary" id="ahg-copy">📋 복사하기</button>
                <button type="button" class="button button-secondary" id="ahg-insert">➕ 에디터에 삽입</button>
                <div id="ahg-code" class="ahg-code-block"></div>
            </div>
        </div>
        <?php
    }
    
    public function enqueue_scripts($hook) {
        if ($hook !== 'post.php' && $hook !== 'post-new.php') {
            return;
        }
        
        wp_enqueue_script(
            'ahg-generator',
            plugin_dir_url(__FILE__) . 'ahg-generator.js',
            array('jquery'),
            '1.0.0',
            true
        );
        
        // JavaScript 파일이 없으면 인라인으로 추가
        add_action('admin_footer', array($this, 'inline_scripts'));
        
        wp_localize_script('ahg-generator', 'ahgAjax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ahg_generate_nonce')
        ));
    }
    
    public function inline_scripts() {
        ?>
        <script>
        jQuery(document).ready(function($) {
            let generatedContent = {
                version1: '',
                version2: ''
            };
            let currentVersion = 2;
            
            function updateVersionTabs() {
                $('.ahg-version-tab').removeClass('active');
                $(`.ahg-version-tab[data-version="${currentVersion}"]`).addClass('active');
                
                const content = currentVersion === 1 ? generatedContent.version1 : generatedContent.version2;
                $('#ahg-code').text(content);
            }
            
            $('.ahg-version-tab').on('click', function() {
                currentVersion = parseInt($(this).data('version'));
                updateVersionTabs();
            });
            
            function generateContent(version) {
                const keyword = $('#ahg-keyword').val().trim();
                if (!keyword) {
                    showError('키워드를 입력해주세요.');
                    return;
                }
                
                const tabs = [];
                const tabLinks = [];
                for (let i = 1; i <= 4; i++) {
                    const tab = $(`#ahg-tab-${i}`).val().trim();
                    const link = $(`#ahg-tab-link-${i}`).val().trim();
                    if (tab) {
                        tabs.push(tab);
                        tabLinks.push(link);
                    }
                }
                
                if (tabs.length === 0) {
                    showError('최소 1개의 탭을 입력해주세요.');
                    return;
                }
                
                $('#ahg-loading').show();
                $('#ahg-error').hide();
                $('#ahg-generate-v1, #ahg-generate-v2').prop('disabled', true);
                
                $.ajax({
                    url: ahgAjax.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'ahg_generate_content',
                        nonce: ahgAjax.nonce,
                        keyword: keyword,
                        tabs: tabs,
                        tab_links: tabLinks,
                        adsense: $('#ahg-adsense').val(),
                        version: version,
                        version_url: version === 'version2' ? $('#ahg-version2-url').val() : $('#ahg-version1-url').val()
                    },
                    success: function(response) {
                        if (response.success) {
                            generatedContent[version] = response.data.content;
                            currentVersion = parseInt(version.replace('version', ''));
                            
                            $('#ahg-result').show();
                            updateVersionTabs();
                            showSuccess('글 생성이 완료되었습니다!');
                            
                            // 버전2 생성 후 버전1 버튼 표시
                            if (version === 'version2') {
                                $('#ahg-generate-v1').show();
                                // 버전1 URL 입력 필드 추가
                                if ($('#ahg-version1-url').length === 0) {
                                    const urlInput = $('<div class="ahg-input-group"><label>버전 1 버튼 링크 URL</label><input type="url" id="ahg-version1-url" placeholder="https://example.com" /></div>');
                                    $('#ahg-version2-url').closest('.ahg-input-group').after(urlInput);
                                }
                            }
                        } else {
                            showError(response.data.message || '생성 중 오류가 발생했습니다.');
                        }
                    },
                    error: function(xhr, status, error) {
                        showError('서버 오류가 발생했습니다: ' + error);
                    },
                    complete: function() {
                        $('#ahg-loading').hide();
                        $('#ahg-generate-v1, #ahg-generate-v2').prop('disabled', false);
                    }
                });
            }
            
            $('#ahg-generate-v1').on('click', function() {
                generateContent('version1');
            });
            
            $('#ahg-generate-v2').on('click', function() {
                generateContent('version2');
            });
            
            $('#ahg-copy').on('click', function() {
                const content = currentVersion === 1 ? generatedContent.version1 : generatedContent.version2;
                
                const textarea = document.createElement('textarea');
                textarea.value = content;
                textarea.style.position = 'fixed';
                textarea.style.left = '-999999px';
                document.body.appendChild(textarea);
                textarea.select();
                
                try {
                    document.execCommand('copy');
                    showSuccess('클립보드에 복사되었습니다!');
                } catch (err) {
                    showError('복사 중 오류가 발생했습니다.');
                }
                
                document.body.removeChild(textarea);
            });
            
            $('#ahg-insert').on('click', function() {
                const content = currentVersion === 1 ? generatedContent.version1 : generatedContent.version2;
                
                if (typeof tinymce !== 'undefined' && tinymce.activeEditor) {
                    tinymce.activeEditor.insertContent(content);
                    showSuccess('에디터에 삽입되었습니다!');
                } else {
                    showError('에디터를 찾을 수 없습니다. 복사 버튼을 사용해주세요.');
                }
            });
            
            function showError(message) {
                $('#ahg-error').text(message).show();
                $('#ahg-success').hide();
            }
            
            function showSuccess(message) {
                $('#ahg-success').text(message).show();
                $('#ahg-error').hide();
                setTimeout(() => $('#ahg-success').fadeOut(), 3000);
            }
        });
        </script>
        <?php
    }
    
    public function ajax_generate_content() {
        check_ajax_referer('ahg_generate_nonce', 'nonce');
        
        $keyword = sanitize_text_field($_POST['keyword']);
        $tabs = array_map('sanitize_text_field', $_POST['tabs']);
        $tab_links = array_map('esc_url_raw', $_POST['tab_links']);
        $adsense = wp_kses_post($_POST['adsense']);
        $version = sanitize_text_field($_POST['version']);
        $version_url = esc_url_raw($_POST['version_url']);
        
        // Gemini API로 콘텐츠 생성
        $content = $this->generate_with_gemini($keyword, $tabs, $tab_links, $adsense, $version, $version_url);
        
        if (is_wp_error($content)) {
            wp_send_json_error(array(
                'message' => $content->get_error_message()
            ));
        }
        
        wp_send_json_success(array(
            'content' => $content
        ));
    }
    
    private function generate_with_gemini($keyword, $tabs, $tab_links, $adsense, $version, $version_url) {
        $settings = get_option($this->option_name, array());
        $current_key_index = get_option($this->current_api_key_index_option, 1);
        
        // API 키 가져오기
        $api_keys = array();
        for ($i = 1; $i <= 5; $i++) {
            if (!empty($settings['api_key_' . $i])) {
                $api_keys[] = $settings['api_key_' . $i];
            }
        }
        
        if (empty($api_keys)) {
            return new WP_Error('no_api_key', 'API 키가 설정되지 않았습니다. 설정 페이지에서 API 키를 입력해주세요.');
        }
        
        // 현재 인덱스 조정
        if ($current_key_index > count($api_keys)) {
            $current_key_index = 1;
            update_option($this->current_api_key_index_option, 1);
        }
        
        $max_attempts = count($api_keys);
        $attempt = 0;
        
        while ($attempt < $max_attempts) {
            $api_key = $api_keys[$current_key_index - 1];
            
            // 1단계: 키워드 분석
            $analysis_prompt = '"' . $keyword . '"라는 키워드를 분석해서 다음 정보를 JSON 형태로 제공해주세요:

{
  "category": "키워드 카테고리 (정부지원금/여행/맛집/부동산/투자/기타 중 하나)",
  "hookingStyle": "후킹 스타일",
  "contentStructure": "콘텐츠 구조 타입",
  "buttonStyle": "버튼 멘트 스타일",
  "targetEmotion": "타겟 감정"
}

응답은 JSON만 제공하고 다른 설명은 하지 마세요.';
            
            $analysis_result = $this->call_gemini_api($api_key, $analysis_prompt);
            
            if (is_wp_error($analysis_result)) {
                // API 키 에러 처리
                if ($this->should_rotate_key($analysis_result)) {
                    $current_key_index++;
                    if ($current_key_index > count($api_keys)) {
                        $current_key_index = 1;
                    }
                    update_option($this->current_api_key_index_option, $current_key_index);
                    $attempt++;
                    continue;
                }
                return $analysis_result;
            }
            
            // JSON 파싱
            $analysis_data = $this->parse_json_response($analysis_result);
            
            // 2단계: 블로그 글 생성
            $template = $this->get_dynamic_template($keyword, $tabs, $tab_links, $adsense, $version, $version_url, $analysis_data);
            
            $blog_prompt = '다음 분석 결과를 바탕으로 "' . $keyword . '"에 대한 맞춤형 블로그 글을 작성해주세요.

카테고리: ' . $analysis_data['category'] . '

작성 요구사항:
1. 후킹 메시지는 2줄로 작성하되, 첫째줄과 둘째줄 각각 15-20글자로 임팩트 있게
2. 최신 정보를 반영
3. 특정 월, 일, 날짜를 절대 언급하지 마세요. "현재", "최근", "올해" 등 사용

아래 템플릿의 모든 [대괄호] 부분을 키워드에 맞게 창의적으로 채워주세요:

' . $template . '

응답은 HTML 코드만 제공해주세요. 설명이나 마크다운은 사용하지 마세요.';
            
            $content = $this->call_gemini_api($api_key, $blog_prompt);
            
            if (is_wp_error($content)) {
                // API 키 에러 처리
                if ($this->should_rotate_key($content)) {
                    $current_key_index++;
                    if ($current_key_index > count($api_keys)) {
                        $current_key_index = 1;
                    }
                    update_option($this->current_api_key_index_option, $current_key_index);
                    $attempt++;
                    continue;
                }
                return $content;
            }
            
            // HTML 정리
            $content = $this->clean_html_response($content);
            
            return $content;
        }
        
        return new WP_Error('all_keys_exhausted', '모든 API 키가 할당량을 초과했습니다. 나중에 다시 시도해주세요.');
    }
    
    private function call_gemini_api($api_key, $prompt) {
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key=' . $api_key;
        
        $body = json_encode(array(
            'contents' => array(
                array(
                    'parts' => array(
                        array('text' => $prompt)
                    )
                )
            ),
            'generationConfig' => array(
                'temperature' => 0.7,
                'maxOutputTokens' => 4096
            )
        ));
        
        $response = wp_remote_post($url, array(
            'headers' => array(
                'Content-Type' => 'application/json'
            ),
            'body' => $body,
            'timeout' => 60
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        // 에러 처리
        if ($status_code !== 200) {
            $error_message = isset($data['error']['message']) ? $data['error']['message'] : 'API 요청 실패';
            return new WP_Error('gemini_api_error', $error_message, array('status' => $status_code));
        }
        
        if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            return $data['candidates'][0]['content']['parts'][0]['text'];
        }
        
        return new WP_Error('invalid_response', 'API 응답 형식이 올바르지 않습니다.');
    }
    
    private function should_rotate_key($error) {
        if (!is_wp_error($error)) {
            return false;
        }
        
        $error_data = $error->get_error_data();
        if (isset($error_data['status'])) {
            $status = $error_data['status'];
            // 429 (Too Many Requests), 403 (Forbidden - quota exceeded)
            if ($status == 429 || $status == 403) {
                return true;
            }
        }
        
        return false;
    }
    
    private function parse_json_response($response) {
        $cleaned = preg_replace('/```json\n?/', '', $response);
        $cleaned = preg_replace('/```\n?/', '', $cleaned);
        $cleaned = trim($cleaned);
        
        $data = json_decode($cleaned, true);
        
        if (!$data) {
            return array(
                'category' => '기타',
                'hookingStyle' => '호기심형',
                'contentStructure' => '정보제공형',
                'buttonStyle' => '확인하기',
                'targetEmotion' => 'curiosity'
            );
        }
        
        return $data;
    }
    
    private function clean_html_response($content) {
        $content = preg_replace('/```html\n?/', '', $content);
        $content = preg_replace('/```\n?/', '', $content);
        $content = preg_replace('/^[^<]*(?=<)/s', '', $content);
        return trim($content);
    }
    
    private function get_dynamic_template($keyword, $tabs, $tab_links, $adsense, $version, $version_url, $analysis_data) {
        $category = $analysis_data['category'];
        
        $templates = array(
            '정부지원금' => array(
                'sections' => $version === 'version1' ? array('신청기간', 'FAQ', '신청절차', '필수서류') : array('혜택금액', '실제후기', '숨겨진혜택', '혜택상세'),
                'icon' => '💰'
            ),
            '여행' => array(
                'sections' => $version === 'version1' ? array('여행코스', '예약방법', '준비물', '교통정보') : array('추천명소', '여행후기', '숨은맛집', '여행꿀팁'),
                'icon' => '✈️'
            ),
            '맛집' => array(
                'sections' => $version === 'version1' ? array('예약방법', '메뉴정보', '위치안내', '주차정보') : array('시그니처메뉴', '방문후기', '숨은메뉴', '가격정보'),
                'icon' => '🍽️'
            ),
            '기타' => array(
                'sections' => $version === 'version1' ? array('이용방법', '신청절차', '준비사항', '주의사항') : array('핵심정보', '이용후기', '추가혜택', '상세정보'),
                'icon' => '⭐'
            )
        );
        
        $template = isset($templates[$category]) ? $templates[$category] : $templates['기타'];
        $sections = $template['sections'];
        $icon = $template['icon'];
        
        $tab_html = '';
        for ($i = 0; $i < count($tabs); $i++) {
            $link = !empty($tab_links[$i]) ? $tab_links[$i] : '#';
            $active = $i === 0 ? ' active' : '';
            $tab_html .= '<li class="tab-item"><a class="tab-link' . $active . '" data-tab="aros' . ($i + 1) . '" href="' . esc_url($link) . '">' . esc_html($tabs[$i]) . '</a></li>' . "\n";
        }
        
        $adsense_code = !empty($adsense) ? $adsense : '<div>
  <script async crossorigin="anonymous" src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-#"></script>
  <ins class="adsbygoogle" data-ad-client="ca-pub-#" data-ad-format="auto" data-ad-slot="#" data-full-width-responsive="true" style="display: block;"></ins>
  <script>(adsbygoogle = window.adsbygoogle || []).push({});</script>
</div>';
        
        return '<!-- 상단 탭 -->
<div class="tab-wrapper">
    <div class="container">
        <nav class="tab-container">
            <ul class="tabs">
' . $tab_html . '            </ul>
        </nav>
    </div>
</div>

<!--1.상단 주목도 높은 메시지-->
<div class="aros-gray-card-center">
    <h3>[첫째줄 15-20글자 강력한 후킹문구]!</h3>
    <h2>[둘째줄 15-20글자 강력한 후킹문구]!</h2>
</div>

<!--애드센스 광고-->
' . $adsense_code . '

<!--2.메뉴 버튼들-->
<div class=".apply-container">
    <div class="link-container">
        <a href="' . esc_url($version_url) . '" class="custom-link">
            <div class="button-container">
                <div class="button-content">
                    <span class="button-text">' . esc_html($keyword) . ' 바로 신청하기</span>
                    <span>→</span>
                </div>
            </div>
        </a>
    </div>
</div>

<!--3.첫 번째 맞춤 섹션-->
<div class="aros-gray-card" style="margin: 20px 0px;">
    <div style="align-items: center; display: flex; justify-content: space-between;">
        <div style="flex: 3 1 0%;">
            <h3>' . esc_html($keyword) . ' ' . $sections[0] . '</h3>
            <p class=".apply-date-text">[' . $sections[0] . ' 관련 핵심 내용]</p>
        </div>
        <div style="flex: 1 1 0%; text-align: right;">
            <div style="font-size: 40px;">' . $icon . '</div>
        </div>
    </div>
</div>

<!--4.두 번째 맞춤 섹션-->
<div class="aros-gray-card" style="margin: 20px 0px;">
    <h3>' . esc_html($keyword) . ' ' . $sections[1] . '</h3>
    <div class="highlight-box requirements">
        <div class="requirement-item">
            <p class="requirement-title">1. [' . $sections[1] . ' 포인트1]</p>
            <p class="requirement-desc">• [구체적인 ' . $sections[1] . ' 정보1]</p>
        </div>
    </div>
</div>';
    }
}

// 플러그인 초기화
new AbaekHomepageGenerator();
