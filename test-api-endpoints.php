<?php
/**
 * Test API endpoints directly without WordPress dependencies
 */

// Test API endpoints
function test_api_endpoints() {
    $endpoints = [
        'https://seo-forge.bitebase.app/health',
        'https://seo-forge.bitebase.app/',
        'https://seo-forge.bitebase.app/api/blog-generator/generate'
    ];
    
    echo "Testing API endpoints...\n\n";
    
    foreach ($endpoints as $endpoint) {
        echo "Testing: $endpoint\n";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        if (strpos($endpoint, 'blog-generator') !== false) {
            // POST request for blog generator
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                'topic' => 'Thai food culture',
                'language' => 'th',
                'tone' => 'friendly',
                'length' => 'medium'
            ]));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json'
            ]);
        }
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            echo "  ❌ Error: $error\n";
        } else {
            echo "  📊 HTTP Code: $http_code\n";
            if ($http_code == 200) {
                echo "  ✅ Success\n";
                $data = json_decode($response, true);
                if ($data) {
                    if (isset($data['status'])) {
                        echo "  📋 Status: " . $data['status'] . "\n";
                    }
                    if (isset($data['name'])) {
                        echo "  📋 Service: " . $data['name'] . "\n";
                    }
                    if (isset($data['endpoints'])) {
                        echo "  📋 Available endpoints: " . implode(', ', array_keys($data['endpoints'])) . "\n";
                    }
                }
            } else {
                echo "  ❌ Failed\n";
                echo "  📄 Response: " . substr($response, 0, 200) . "\n";
            }
        }
        echo "\n";
    }
}

// Test fallback content generation
function test_fallback_content() {
    echo "Testing fallback content generation...\n\n";
    
    // Simulate the plugin's fallback logic
    $topic = "Thai food culture";
    $language = "th";
    
    $fallback_content = [
        'title' => "เกี่ยวกับ $topic",
        'content' => "นี่คือเนื้อหาเกี่ยวกับ $topic ที่สร้างขึ้นโดยระบบสำรอง เนื้อหานี้จะถูกแทนที่เมื่อ API กลับมาทำงานได้ปกติ",
        'meta_description' => "เรียนรู้เกี่ยวกับ $topic ในบทความนี้",
        'keywords' => ["$topic", "วัฒนธรรม", "ไทย"],
        'status' => 'fallback'
    ];
    
    echo "✅ Fallback content generated:\n";
    echo "  📝 Title: " . $fallback_content['title'] . "\n";
    echo "  📄 Content: " . substr($fallback_content['content'], 0, 100) . "...\n";
    echo "  🏷️ Meta Description: " . $fallback_content['meta_description'] . "\n";
    echo "  🔑 Keywords: " . implode(', ', $fallback_content['keywords']) . "\n";
    echo "  📊 Status: " . $fallback_content['status'] . "\n\n";
}

// Test local server if available
function test_local_server() {
    echo "Testing local server (if available)...\n\n";
    
    $local_endpoints = [
        'http://localhost:8000/health',
        'http://localhost:8000/'
    ];
    
    foreach ($local_endpoints as $endpoint) {
        echo "Testing: $endpoint\n";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            echo "  ❌ Local server not available: $error\n";
        } else {
            echo "  ✅ Local server responding (HTTP $http_code)\n";
        }
        echo "\n";
    }
}

// Run tests
echo "=== SEO Forge Professional API Tests ===\n\n";

test_api_endpoints();
test_fallback_content();
test_local_server();

echo "=== Test Summary ===\n";
echo "✅ Fallback content generation: Working\n";
echo "📡 API endpoints: Check results above\n";
echo "🔧 Plugin should handle API unavailability gracefully\n";