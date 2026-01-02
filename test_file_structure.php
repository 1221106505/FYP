<?php
/**
 * 文件依赖关系分析器
 * 可视化展示文件之间的包含/引用关系
 */

class FileDependencyAnalyzer {
    
    private $rootDir;
    private $fileExtensions = ['php', 'html', 'js', 'css', 'twig', 'blade.php'];
    private $dependencies = [];
    private $visitedFiles = [];
    private $fileContents = [];
    private $graphData = [];
    
    public function __construct($rootDir = '.') {
        $this->rootDir = realpath($rootDir);
        if (!$this->rootDir) {
            throw new Exception("目录不存在: $rootDir");
        }
    }
    
    /**
     * 分析整个目录的文件依赖关系
     */
    public function analyzeDirectory($maxDepth = 5) {
        $this->dependencies = [];
        $this->visitedFiles = [];
        
        // 获取所有可分析的文件
        $files = $this->getAllFiles($this->rootDir, $maxDepth);
        
        echo "发现 " . count($files) . " 个文件\n";
        
        // 分析每个文件的依赖
        foreach ($files as $file) {
            $this->analyzeFile($file);
        }
        
        // 构建依赖图
        $this->buildDependencyGraph();
        
        return $this->graphData;
    }
    
    /**
     * 获取目录下所有文件
     */
    private function getAllFiles($dir, $maxDepth, $currentDepth = 0) {
        if ($currentDepth > $maxDepth) {
            return [];
        }
        
        $files = [];
        $items = scandir($dir);
        
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            
            if (is_dir($path)) {
                $subFiles = $this->getAllFiles($path, $maxDepth, $currentDepth + 1);
                $files = array_merge($files, $subFiles);
            } elseif ($this->isAnalyzableFile($path)) {
                $files[] = $path;
            }
        }
        
        return $files;
    }
    
    /**
     * 检查文件是否可分析
     */
    private function isAnalyzableFile($filepath) {
        $extension = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));
        return in_array($extension, $this->fileExtensions);
    }
    
    /**
     * 分析单个文件的依赖
     */
    private function analyzeFile($filepath) {
        if (isset($this->visitedFiles[$filepath])) {
            return $this->visitedFiles[$filepath];
        }
        
        $relativePath = $this->getRelativePath($filepath);
        echo "分析: $relativePath\n";
        
        $content = file_get_contents($filepath);
        $this->fileContents[$filepath] = $content;
        
        $deps = array(
            'file' => $relativePath,
            'includes' => array(),
            'required' => array(),
            'imports' => array(),
            'links' => array(),
            'scripts' => array(),
            'images' => array(),
            'classes' => array(),
            'functions' => array(),
            'type' => pathinfo($filepath, PATHINFO_EXTENSION),
        );
        
        // 根据文件类型分析依赖
        switch (strtolower($deps['type'])) {
            case 'php':
                $phpAnalysis = $this->analyzePHPFile($content, $filepath);
                $deps = array_merge($deps, $phpAnalysis);
                break;
            case 'html':
            case 'htm':
                $htmlAnalysis = $this->analyzeHTMLFile($content, $filepath);
                $deps = array_merge($deps, $htmlAnalysis);
                break;
            case 'js':
                $jsAnalysis = $this->analyzeJSFile($content, $filepath);
                $deps = array_merge($deps, $jsAnalysis);
                break;
            case 'css':
                $cssAnalysis = $this->analyzeCSSFile($content, $filepath);
                $deps = array_merge($deps, $cssAnalysis);
                break;
        }
        
        $this->dependencies[$relativePath] = $deps;
        $this->visitedFiles[$filepath] = $deps;
        
        return $deps;
    }
    
    /**
     * 分析PHP文件
     */
    private function analyzePHPFile($content, $filepath) {
        $analysis = array(
            'includes' => array(),
            'required' => array(),
            'classes' => array(),
            'functions' => array(),
            'namespaces' => array(),
        );
        
        // 分析 include/require
        $patterns = array(
            'include' => '/(include|include_once)\s*[\'"]([^\'"]+)[\'"]/i',
            'require' => '/(require|require_once)\s*[\'"]([^\'"]+)[\'"]/i',
        );
        
        foreach ($patterns as $type => $pattern) {
            if (preg_match_all($pattern, $content, $matches)) {
                foreach ($matches[2] as $file) {
                    $resolved = $this->resolvePath($file, $filepath);
                    if ($resolved) {
                        if ($type === 'include') {
                            $analysis['includes'][] = $resolved;
                        } else {
                            $analysis['required'][] = $resolved;
                        }
                    }
                }
            }
        }
        
        // 分析类
        if (preg_match_all('/class\s+(\w+)/', $content, $matches)) {
            $analysis['classes'] = $matches[1];
        }
        
        // 分析函数
        if (preg_match_all('/function\s+(\w+)\s*\(/', $content, $matches)) {
            $analysis['functions'] = $matches[1];
        }
        
        // 分析命名空间
        if (preg_match_all('/namespace\s+([^;]+);/', $content, $matches)) {
            $analysis['namespaces'] = $matches[1];
        }
        
        return $analysis;
    }
    
    /**
     * 分析HTML文件
     */
    private function analyzeHTMLFile($content, $filepath) {
        $analysis = array(
            'links' => array(),
            'scripts' => array(),
            'images' => array(),
            'iframes' => array(),
            'stylesheets' => array(),
        );
        
        // 分析CSS链接
        if (preg_match_all('/<link\s+[^>]*href=["\']([^"\']+)["\'][^>]*>/i', $content, $matches)) {
            foreach ($matches[1] as $href) {
                $resolved = $this->resolvePath($href, $filepath);
                if ($resolved) {
                    $analysis['links'][] = $resolved;
                }
            }
        }
        
        // 分析script src
        if (preg_match_all('/<script\s+[^>]*src=["\']([^"\']+)["\'][^>]*>/i', $content, $matches)) {
            foreach ($matches[1] as $src) {
                $resolved = $this->resolvePath($src, $filepath);
                if ($resolved) {
                    $analysis['scripts'][] = $resolved;
                }
            }
        }
        
        // 分析图片
        if (preg_match_all('/<img\s+[^>]*src=["\']([^"\']+)["\'][^>]*>/i', $content, $matches)) {
            foreach ($matches[1] as $src) {
                $resolved = $this->resolvePath($src, $filepath);
                if ($resolved) {
                    $analysis['images'][] = $resolved;
                }
            }
        }
        
        // 分析iframe
        if (preg_match_all('/<iframe\s+[^>]*src=["\']([^"\']+)["\'][^>]*>/i', $content, $matches)) {
            foreach ($matches[1] as $src) {
                $resolved = $this->resolvePath($src, $filepath);
                if ($resolved) {
                    $analysis['iframes'][] = $resolved;
                }
            }
        }
        
        return $analysis;
    }
    
    /**
     * 分析JavaScript文件
     */
    private function analyzeJSFile($content, $filepath) {
        $analysis = array(
            'imports' => array(),
            'requires' => array(),
        );
        
        // ES6 imports
        if (preg_match_all('/import\s+(?:.*?from\s+)?[\'"]([^"\']+)[\'"]/i', $content, $matches)) {
            foreach ($matches[1] as $import) {
                $resolved = $this->resolvePath($import, $filepath);
                if ($resolved) {
                    $analysis['imports'][] = $resolved;
                }
            }
        }
        
        // CommonJS require
        if (preg_match_all('/require\s*\([\'"]([^"\']+)[\'"]\)/i', $content, $matches)) {
            foreach ($matches[1] as $require) {
                $resolved = $this->resolvePath($require, $filepath);
                if ($resolved) {
                    $analysis['requires'][] = $resolved;
                }
            }
        }
        
        return $analysis;
    }
    
    /**
     * 分析CSS文件
     */
    private function analyzeCSSFile($content, $filepath) {
        $analysis = array(
            'imports' => array(),
            'urls' => array(),
        );
        
        // @import
        if (preg_match_all('/@import\s+(?:url\()?["\']?([^"\')]+)["\']?\)?/i', $content, $matches)) {
            foreach ($matches[1] as $import) {
                $resolved = $this->resolvePath($import, $filepath);
                if ($resolved) {
                    $analysis['imports'][] = $resolved;
                }
            }
        }
        
        // url()
        if (preg_match_all('/url\s*\(["\']?([^"\')]+)["\']?\)/i', $content, $matches)) {
            foreach ($matches[1] as $url) {
                $resolved = $this->resolvePath($url, $filepath);
                if ($resolved) {
                    $analysis['urls'][] = $resolved;
                }
            }
        }
        
        return $analysis;
    }
    
    /**
     * 解析路径为相对路径
     */
    private function resolvePath($path, $baseFile) {
        // 移除查询字符串和片段
        $path = preg_replace('/[?#].*$/', '', $path);
        
        // 如果是绝对URL，跳过
        if (preg_match('/^(https?:|\/\/)/i', $path)) {
            return null;
        }
        
        // 如果是绝对路径
        if (strpos($path, '/') === 0) {
            $absolute = $this->rootDir . $path;
        } else {
            // 相对路径
            $baseDir = dirname($baseFile);
            $absolute = realpath($baseDir . DIRECTORY_SEPARATOR . $path);
        }
        
        if ($absolute && file_exists($absolute)) {
            return $this->getRelativePath($absolute);
        }
        
        return null;
    }
    
    /**
     * 获取相对根目录的路径
     */
    private function getRelativePath($absolutePath) {
        return str_replace($this->rootDir . DIRECTORY_SEPARATOR, '', $absolutePath);
    }
    
    /**
     * 构建依赖图数据
     */
    private function buildDependencyGraph() {
        $this->graphData = array(
            'nodes' => array(),
            'edges' => array(),
            'clusters' => array(),
        );
        
        // 创建节点
        foreach ($this->dependencies as $file => $deps) {
            $nodeId = $this->getNodeId($file);
            
            $this->graphData['nodes'][] = array(
                'id' => $nodeId,
                'label' => $file,
                'type' => $deps['type'],
                'size' => $this->getFileSize($file),
                'color' => $this->getFileColor($deps['type']),
                'properties' => $deps,
            );
        }
        
        // 创建边（依赖关系）
        foreach ($this->dependencies as $sourceFile => $deps) {
            $sourceId = $this->getNodeId($sourceFile);
            
            // 添加所有类型的依赖关系
            $dependencyTypes = array(
                'includes' => 'include',
                'required' => 'require',
                'imports' => 'import',
                'links' => 'link',
                'scripts' => 'script',
                'images' => 'image',
                'requires' => 'require_js',
            );
            
            foreach ($dependencyTypes as $type => $edgeType) {
                if (!empty($deps[$type])) {
                    foreach ($deps[$type] as $targetFile) {
                        if ($this->hasNode($targetFile)) {
                            $targetId = $this->getNodeId($targetFile);
                            
                            $this->graphData['edges'][] = array(
                                'from' => $sourceId,
                                'to' => $targetId,
                                'type' => $edgeType,
                                'label' => $this->getEdgeLabel($edgeType),
                                'color' => $this->getEdgeColor($edgeType),
                                'arrows' => 'to',
                                'dashes' => $edgeType === 'image' || $edgeType === 'link',
                            );
                        }
                    }
                }
            }
        }
        
        // 按目录分组创建集群
        $this->createClusters();
    }
    
    /**
     * 按目录创建集群
     */
    private function createClusters() {
        $clusters = array();
        
        foreach ($this->dependencies as $file => $deps) {
            $dir = dirname($file);
            if ($dir === '.') {
                $dir = '根目录';
            }
            
            if (!isset($clusters[$dir])) {
                $clusters[$dir] = array(
                    'id' => 'cluster_' . md5($dir),
                    'label' => $dir,
                    'nodes' => array(),
                );
            }
            
            $clusters[$dir]['nodes'][] = $this->getNodeId($file);
        }
        
        $this->graphData['clusters'] = array_values($clusters);
    }
    
    /**
     * 获取节点ID
     */
    private function getNodeId($file) {
        return 'node_' . md5($file);
    }
    
    /**
     * 检查节点是否存在
     */
    private function hasNode($file) {
        return isset($this->dependencies[$file]);
    }
    
    /**
     * 获取文件大小（用于节点大小）
     */
    private function getFileSize($relativePath) {
        $absolute = $this->rootDir . DIRECTORY_SEPARATOR . $relativePath;
        if (file_exists($absolute)) {
            $size = filesize($absolute);
            return min(50, max(20, $size / 1024)); // 基于文件大小缩放
        }
        return 30;
    }
    
    /**
     * 根据文件类型获取颜色
     */
    private function getFileColor($type) {
        $colors = array(
            'php' => '#4F5D95',
            'html' => '#E44D26',
            'htm' => '#E44D26',
            'js' => '#F7DF1E',
            'css' => '#1572B6',
            'twig' => '#C1D82F',
            'blade.php' => '#F55247',
        );
        
        return isset($colors[$type]) ? $colors[$type] : '#888888';
    }
    
    /**
     * 获取边标签
     */
    private function getEdgeLabel($type) {
        $labels = array(
            'include' => '包含',
            'require' => '必需',
            'import' => '导入',
            'link' => '链接',
            'script' => '脚本',
            'image' => '图片',
            'require_js' => 'JS引用',
        );
        
        return isset($labels[$type]) ? $labels[$type] : $type;
    }
    
    /**
     * 获取边颜色
     */
    private function getEdgeColor($type) {
        $colors = array(
            'include' => '#FF6B6B',
            'require' => '#FFA726',
            'import' => '#66BB6A',
            'link' => '#42A5F5',
            'script' => '#AB47BC',
            'image' => '#26A69A',
            'require_js' => '#7E57C2',
        );
        
        return isset($colors[$type]) ? $colors[$type] : '#CCCCCC';
    }
    
    /**
     * 生成可视化HTML
     */
    public function generateVisualization($title = '文件依赖关系图') {
        $graphData = json_encode($this->graphData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        
        $html = <<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Microsoft YaHei', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1600px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
            font-weight: 300;
        }
        
        .header .subtitle {
            font-size: 16px;
            opacity: 0.9;
        }
        
        .controls {
            background: #f8f9fa;
            padding: 20px 30px;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: center;
        }
        
        .control-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .control-group label {
            font-weight: 600;
            color: #495057;
        }
        
        select, input[type="range"] {
            padding: 8px 12px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            background: white;
            color: #495057;
            font-size: 14px;
        }
        
        .stats {
            background: #e9ecef;
            padding: 15px 30px;
            display: flex;
            gap: 30px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .stat-item {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: 700;
            color: #495057;
        }
        
        .stat-label {
            color: #6c757d;
            font-size: 14px;
        }
        
        .main-content {
            display: flex;
            height: 700px;
        }
        
        .graph-container {
            flex: 3;
            border-right: 1px solid #dee2e6;
            position: relative;
        }
        
        #dependencyGraph {
            width: 100%;
            height: 100%;
        }
        
        .sidebar {
            flex: 1;
            padding: 25px;
            background: #f8f9fa;
            overflow-y: auto;
            max-width: 350px;
        }
        
        .sidebar-section {
            margin-bottom: 30px;
        }
        
        .sidebar-section h3 {
            color: #495057;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #dee2e6;
            font-size: 18px;
        }
        
        .file-list {
            list-style: none;
        }
        
        .file-item {
            padding: 10px 15px;
            margin-bottom: 8px;
            background: white;
            border-radius: 8px;
            border-left: 4px solid #667eea;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .file-item:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .file-item.selected {
            background: #e3f2fd;
            border-left-color: #2196f3;
        }
        
        .file-icon {
            width: 24px;
            height: 24px;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 12px;
            font-weight: bold;
        }
        
        .file-name {
            flex: 1;
            font-size: 14px;
            color: #495057;
            word-break: break-all;
        }
        
        .file-type {
            font-size: 12px;
            color: #6c757d;
            background: #e9ecef;
            padding: 2px 8px;
            border-radius: 10px;
        }
        
        .dependency-list {
            list-style: none;
        }
        
        .dependency-item {
            padding: 8px 12px;
            margin-bottom: 5px;
            background: white;
            border-radius: 6px;
            font-size: 13px;
            color: #495057;
            border-left: 3px solid #66BB6A;
        }
        
        .legend {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 20px;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            color: #6c757d;
        }
        
        .legend-color {
            width: 16px;
            height: 16px;
            border-radius: 3px;
        }
        
        .tooltip {
            position: absolute;
            background: white;
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            max-width: 300px;
            display: none;
            z-index: 1000;
            pointer-events: none;
        }
        
        .tooltip h4 {
            color: #495057;
            margin-bottom: 10px;
            font-size: 16px;
        }
        
        .tooltip-content {
            font-size: 13px;
            color: #6c757d;
            line-height: 1.5;
        }
        
        .footer {
            background: #f8f9fa;
            padding: 20px 30px;
            text-align: center;
            color: #6c757d;
            font-size: 14px;
            border-top: 1px solid #dee2e6;
        }
        
        .loading {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 18px;
            color: #667eea;
        }
        
        .no-deps {
            color: #6c757d;
            font-style: italic;
            text-align: center;
            padding: 20px;
        }
    </style>
    <script type="text/javascript" src="https://unpkg.com/vis-network/standalone/umd/vis-network.min.js"></script>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📁 {$title}</h1>
            <div class="subtitle">可视化展示文件之间的包含和引用关系</div>
        </div>
        
        <div class="controls">
            <div class="control-group">
                <label>布局引擎:</label>
                <select id="layoutSelector">
                    <option value="hierarchical">分层布局</option>
                    <option value="force">力导向布局</option>
                    <option value="circular">圆形布局</option>
                </select>
            </div>
            
            <div class="control-group">
                <label>物理引擎:</label>
                <select id="physicsSelector">
                    <option value="forceAtlas2Based">力导向</option>
                    <option value="barnesHut">Barnes-Hut</option>
                    <option value="repulsion">斥力</option>
                    <option value="false">关闭</option>
                </select>
            </div>
            
            <div class="control-group">
                <label>节点大小:</label>
                <input type="range" id="nodeSizeSlider" min="10" max="100" value="30">
            </div>
            
            <button onclick="exportGraph()" style="margin-left: auto; padding: 8px 20px; background: #667eea; color: white; border: none; border-radius: 8px; cursor: pointer;">
                📥 导出图片
            </button>
        </div>
        
        <div class="stats">
            <div class="stat-item">
                <div class="stat-value" id="nodeCount">0</div>
                <div class="stat-label">文件数量</div>
            </div>
            <div class="stat-item">
                <div class="stat-value" id="edgeCount">0</div>
                <div class="stat-label">依赖关系</div>
            </div>
            <div class="stat-item">
                <div class="stat-value" id="clusterCount">0</div>
                <div class="stat-label">目录分组</div>
            </div>
        </div>
        
        <div class="main-content">
            <div class="graph-container">
                <div id="dependencyGraph"></div>
                <div class="loading" id="loading">正在加载依赖图...</div>
                <div class="tooltip" id="graphTooltip"></div>
            </div>
            
            <div class="sidebar">
                <div class="sidebar-section">
                    <h3>📂 文件列表</h3>
                    <ul class="file-list" id="fileList"></ul>
                </div>
                
                <div class="sidebar-section">
                    <h3>🔗 依赖关系</h3>
                    <div id="dependencyDetails">
                        <p class="no-deps">点击左侧文件查看其依赖</p>
                    </div>
                </div>
                
                <div class="sidebar-section">
                    <h3>🎨 图例说明</h3>
                    <div class="legend">
                        <div class="legend-item">
                            <div class="legend-color" style="background: #4F5D95;"></div>
                            <span>PHP 文件</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color" style="background: #E44D26;"></div>
                            <span>HTML 文件</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color" style="background: #F7DF1E;"></div>
                            <span>JS 文件</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color" style="background: #1572B6;"></div>
                            <span>CSS 文件</span>
                        </div>
                    </div>
                    <div class="legend" style="margin-top: 10px;">
                        <div class="legend-item">
                            <div class="legend-color" style="background: #FF6B6B;"></div>
                            <span>包含关系</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color" style="background: #FFA726;"></div>
                            <span>必需关系</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color" style="background: #66BB6A;"></div>
                            <span>导入关系</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="footer">
            <p>文件依赖关系分析工具 | 生成时间: <span id="generatedTime"></span></p>
        </div>
    </div>
    
    <script>
        // 从PHP传递的数据
        const graphData = {$graphData};
        let network = null;
        let selectedNode = null;
        
        document.addEventListener('DOMContentLoaded', function() {
            // 显示生成时间
            document.getElementById('generatedTime').textContent = new Date().toLocaleString();
            
            // 初始化统计
            updateStats();
            
            // 初始化文件列表
            updateFileList();
            
            // 初始化依赖图
            initDependencyGraph();
            
            // 绑定控件事件
            document.getElementById('layoutSelector').addEventListener('change', updateLayout);
            document.getElementById('physicsSelector').addEventListener('change', updatePhysics);
            document.getElementById('nodeSizeSlider').addEventListener('input', updateNodeSize);
            
            // 隐藏加载提示
            document.getElementById('loading').style.display = 'none';
        });
        
        function updateStats() {
            document.getElementById('nodeCount').textContent = graphData.nodes.length;
            document.getElementById('edgeCount').textContent = graphData.edges.length;
            document.getElementById('clusterCount').textContent = graphData.clusters.length;
        }
        
        function updateFileList() {
            const fileList = document.getElementById('fileList');
            fileList.innerHTML = '';
            
            graphData.nodes.forEach(function(node) {
                const li = document.createElement('li');
                li.className = 'file-item';
                li.dataset.nodeId = node.id;
                li.onclick = function() { selectFile(node.id); };
                
                // 文件类型图标
                const icon = document.createElement('div');
                icon.className = 'file-icon';
                icon.style.backgroundColor = node.color;
                icon.textContent = node.type.toUpperCase().substring(0, 3);
                
                // 文件名
                const name = document.createElement('div');
                name.className = 'file-name';
                name.textContent = node.label;
                
                // 文件类型标签
                const type = document.createElement('div');
                type.className = 'file-type';
                type.textContent = node.type;
                
                li.appendChild(icon);
                li.appendChild(name);
                li.appendChild(type);
                fileList.appendChild(li);
            });
        }
        
        function initDependencyGraph() {
            // 创建节点数据
            const nodeData = [];
            for (let i = 0; i < graphData.nodes.length; i++) {
                const node = graphData.nodes[i];
                nodeData.push({
                    id: node.id,
                    label: node.label.split('/').pop(), // 只显示文件名
                    title: node.label, // 完整路径作为悬停提示
                    size: node.size,
                    color: {
                        background: node.color,
                        border: '#2c3e50',
                        highlight: {
                            background: node.color,
                            border: '#3498db'
                        }
                    },
                    font: {
                        size: 14,
                        color: '#2c3e50'
                    },
                    borderWidth: 2,
                    shape: 'dot'
                });
            }
            
            // 创建边数据
            const edgeData = [];
            for (let i = 0; i < graphData.edges.length; i++) {
                const edge = graphData.edges[i];
                
                // 查找源节点和目标节点的标签
                let sourceLabel = '';
                let targetLabel = '';
                for (let j = 0; j < graphData.nodes.length; j++) {
                    if (graphData.nodes[j].id === edge.from) {
                        sourceLabel = graphData.nodes[j].label;
                    }
                    if (graphData.nodes[j].id === edge.to) {
                        targetLabel = graphData.nodes[j].label;
                    }
                }
                
                edgeData.push({
                    from: edge.from,
                    to: edge.to,
                    label: edge.label,
                    color: {
                        color: edge.color,
                        highlight: edge.color,
                        hover: edge.color
                    },
                    arrows: edge.arrows,
                    dashes: edge.dashes,
                    width: 2,
                    title: edge.type + ': ' + sourceLabel + ' -> ' + targetLabel
                });
            }
            
            // 创建容器
            const container = document.getElementById('dependencyGraph');
            const data = { 
                nodes: new vis.DataSet(nodeData), 
                edges: new vis.DataSet(edgeData) 
            };
            
            // 配置选项
            const options = {
                layout: {
                    hierarchical: {
                        enabled: true,
                        direction: 'UD',
                        sortMethod: 'hubsize',
                        levelSeparation: 200,
                        nodeSpacing: 150
                    }
                },
                physics: {
                    enabled: true,
                    solver: 'forceAtlas2Based',
                    forceAtlas2Based: {
                        gravitationalConstant: -100,
                        centralGravity: 0.01,
                        springLength: 200,
                        springConstant: 0.08,
                        damping: 0.4,
                        avoidOverlap: 1
                    }
                },
                interaction: {
                    hover: true,
                    tooltipDelay: 200,
                    navigationButtons: true,
                    keyboard: true
                },
                nodes: {
                    shape: 'dot',
                    scaling: {
                        min: 20,
                        max: 60,
                        label: {
                            enabled: true,
                            min: 14,
                            max: 30
                        }
                    }
                },
                edges: {
                    smooth: {
                        type: 'continuous',
                        roundness: 0.5
                    },
                    scaling: {
                        min: 1,
                        max: 3
                    }
                },
                groups: {
                    php: { color: '#4F5D95' },
                    html: { color: '#E44D26' },
                    js: { color: '#F7DF1E' },
                    css: { color: '#1572B6' }
                }
            };
            
            // 创建网络
            network = new vis.Network(container, data, options);
            
            // 事件监听
            network.on('click', function(params) {
                if (params.nodes.length > 0) {
                    selectFile(params.nodes[0]);
                }
            });
            
            network.on('hoverNode', function(params) {
                const nodeId = params.node;
                // 查找节点
                let node = null;
                for (let i = 0; i < graphData.nodes.length; i++) {
                    if (graphData.nodes[i].id === nodeId) {
                        node = graphData.nodes[i];
                        break;
                    }
                }
                if (node) {
                    showTooltip(params.event, node);
                }
            });
            
            network.on('blurNode', function() {
                hideTooltip();
            });
            
            // 添加集群
            addClustersToGraph();
        }
        
        function addClustersToGraph() {
            // 使用分组功能实现集群效果
            const nodes = network.body.data.nodes;
            for (let i = 0; i < graphData.clusters.length; i++) {
                const cluster = graphData.clusters[i];
                // 为集群中的节点添加分组
                for (let j = 0; j < cluster.nodes.length; j++) {
                    const nodeId = cluster.nodes[j];
                    const node = nodes.get(nodeId);
                    if (node) {
                        // 可以根据目录结构设置不同的分组
                        const group = cluster.label.replace(/[^a-zA-Z0-9]/g, '_');
                        nodes.update({ id: nodeId, group: group });
                    }
                }
            }
        }
        
        function selectFile(nodeId) {
            // 更新左侧列表选中状态
            const fileItems = document.querySelectorAll('.file-item');
            for (let i = 0; i < fileItems.length; i++) {
                fileItems[i].classList.remove('selected');
                if (fileItems[i].dataset.nodeId === nodeId) {
                    fileItems[i].classList.add('selected');
                }
            }
            
            // 在图中高亮节点
            network.selectNodes([nodeId]);
            network.fit({
                nodes: [nodeId],
                animation: true
            });
            
            // 显示依赖详情
            showDependencyDetails(nodeId);
            
            selectedNode = nodeId;
        }
        
        function showDependencyDetails(nodeId) {
            // 查找节点
            let node = null;
            for (let i = 0; i < graphData.nodes.length; i++) {
                if (graphData.nodes[i].id === nodeId) {
                    node = graphData.nodes[i];
                    break;
                }
            }
            
            const container = document.getElementById('dependencyDetails');
            
            if (!node) {
                container.innerHTML = '<p class="no-deps">未找到文件信息</p>';
                return;
            }
            
            const props = node.properties;
            let html = `
                <div style="margin-bottom: 20px;">
                    <h4 style="color: #495057; margin-bottom: 10px;">${node.label}</h4>
                    <div style="font-size: 13px; color: #6c757d;">
                        类型: ${props.type} | 
                        节点ID: ${node.id}
                    </div>
                </div>
            `;
            
            // 显示依赖关系
            const dependencyTypes = {
                'includes': '包含的文件',
                'required': '必需的文件',
                'imports': '导入的文件',
                'links': '链接的文件',
                'scripts': '引用的脚本',
                'images': '引用的图片',
                'classes': '定义的类',
                'functions': '定义的函数',
                'namespaces': '命名空间'
            };
            
            html += '<div>';
            
            for (const key in dependencyTypes) {
                if (props[key] && props[key].length > 0) {
                    html += `<h5 style="color: #6c757d; margin: 15px 0 8px 0;">${dependencyTypes[key]}:</h5><ul class="dependency-list">`;
                    for (let i = 0; i < props[key].length; i++) {
                        html += `<li class="dependency-item">${props[key][i]}</li>`;
                    }
                    html += '</ul>';
                }
            }
            
            html += '</div>';
            
            // 如果没有依赖
            let hasDependencies = false;
            for (const key in dependencyTypes) {
                if (props[key] && props[key].length > 0) {
                    hasDependencies = true;
                    break;
                }
            }
            
            if (!hasDependencies) {
                html += '<p class="no-deps">此文件没有检测到依赖关系</p>';
            }
            
            container.innerHTML = html;
            
            // 为依赖项添加点击事件
            const depItems = container.querySelectorAll('.dependency-item');
            for (let i = 0; i < depItems.length; i++) {
                depItems[i].style.cursor = 'pointer';
                depItems[i].onclick = function() {
                    const targetFile = this.textContent;
                    // 查找目标节点
                    let targetNode = null;
                    for (let j = 0; j < graphData.nodes.length; j++) {
                        if (graphData.nodes[j].label === targetFile) {
                            targetNode = graphData.nodes[j];
                            break;
                        }
                    }
                    if (targetNode) {
                        selectFile(targetNode.id);
                    }
                };
            }
        }
        
        function showTooltip(event, node) {
            const tooltip = document.getElementById('graphTooltip');
            const props = node.properties;
            
            let content = `
                <h4>${node.label}</h4>
                <div class="tooltip-content">
                    <strong>类型:</strong> ${props.type}<br>
                    <strong>大小:</strong> ${Math.round(node.size * 1024)} 字节<br>
            `;
            
            // 计算依赖统计
            let depCount = 0;
            let refCount = 0;
            for (let i = 0; i < graphData.edges.length; i++) {
                if (graphData.edges[i].from === node.id) {
                    depCount++;
                }
                if (graphData.edges[i].to === node.id) {
                    refCount++;
                }
            }
            
            content += `
                    <strong>依赖文件:</strong> ${depCount} 个<br>
                    <strong>被引用:</strong> ${refCount} 次<br>
            `;
            
            if (props.classes && props.classes.length > 0) {
                content += `<strong>类:</strong> ${props.classes.join(', ')}<br>`;
            }
            
            content += '</div>';
            
            tooltip.innerHTML = content;
            tooltip.style.display = 'block';
            tooltip.style.left = (event.clientX + 15) + 'px';
            tooltip.style.top = (event.clientY + 15) + 'px';
        }
        
        function hideTooltip() {
            document.getElementById('graphTooltip').style.display = 'none';
        }
        
        function updateLayout() {
            const layout = document.getElementById('layoutSelector').value;
            const options = network.getOptions();
            
            if (layout === 'hierarchical') {
                options.layout = {
                    hierarchical: {
                        enabled: true,
                        direction: 'UD',
                        sortMethod: 'hubsize'
                    }
                };
            } else if (layout === 'force') {
                options.layout = { hierarchical: { enabled: false } };
                options.physics.solver = 'forceAtlas2Based';
            } else if (layout === 'circular') {
                options.layout = {
                    hierarchical: { enabled: false }
                };
                options.physics.solver = 'repulsion';
                network.setOptions(options);
                network.stabilize();
            }
            
            network.setOptions(options);
        }
        
        function updatePhysics() {
            const physics = document.getElementById('physicsSelector').value;
            const options = network.getOptions();
            
            if (physics === 'false') {
                options.physics.enabled = false;
            } else {
                options.physics.enabled = true;
                options.physics.solver = physics;
            }
            
            network.setOptions(options);
        }
        
        function updateNodeSize() {
            const size = document.getElementById('nodeSizeSlider').value;
            const nodes = network.body.data.nodes;
            const nodeIds = nodes.getIds();
            const updates = [];
            for (let i = 0; i < nodeIds.length; i++) {
                updates.push({
                    id: nodeIds[i],
                    size: parseInt(size)
                });
            }
            nodes.update(updates);
        }
        
        function exportGraph() {
            network.storePositions();
            const canvas = network.canvas.frame.canvas;
            const dataUrl = canvas.toDataURL('image/png');
            
            const link = document.createElement('a');
            link.download = '文件依赖关系图.png';
            link.href = dataUrl;
            link.click();
            
            alert('图片已导出！');
        }
        
        // 处理窗口大小变化
        window.addEventListener('resize', function() {
            if (network) {
                network.redraw();
            }
        });
        
        // 处理鼠标移动
        document.addEventListener('mousemove', function(event) {
            const tooltip = document.getElementById('graphTooltip');
            if (tooltip.style.display === 'block') {
                tooltip.style.left = (event.clientX + 15) + 'px';
                tooltip.style.top = (event.clientY + 15) + 'px';
            }
        });
    </script>
</body>
</html>
HTML;

        return $html;
    }
    
    /**
     * 生成文本格式的依赖树
     */
    public function generateDependencyTree($startFile = null) {
        if ($startFile === null && !empty($this->dependencies)) {
            $keys = array_keys($this->dependencies);
            $startFile = $keys[0];
        }
        
        $tree = $this->buildDependencyTree($startFile);
        
        $output = "📁 文件依赖关系树\n";
        $output .= str_repeat("=", 60) . "\n\n";
        $output .= $this->formatTree($tree);
        
        return $output;
    }
    
    /**
     * 构建依赖树
     */
    private function buildDependencyTree($file, $visited = array(), $level = 0) {
        if ($level > 10 || in_array($file, $visited)) {
            return array('file' => $file, 'circular' => true, 'deps' => array());
        }
        
        $visited[] = $file;
        
        if (!isset($this->dependencies[$file])) {
            return array('file' => $file, 'not_found' => true, 'deps' => array());
        }
        
        $deps = $this->dependencies[$file];
        $node = array(
            'file' => $file,
            'type' => $deps['type'],
            'deps' => array(),
        );
        
        // 收集所有依赖
        $allDeps = array_merge(
            $deps['includes'],
            $deps['required'],
            $deps['imports'],
            $deps['links'],
            $deps['scripts']
        );
        
        foreach (array_unique($allDeps) as $dep) {
            if (isset($this->dependencies[$dep])) {
                $node['deps'][] = $this->buildDependencyTree($dep, $visited, $level + 1);
            }
        }
        
        return $node;
    }
    
    /**
     * 格式化树形输出
     */
    private function formatTree($node, $prefix = '', $isLast = true) {
        $output = '';
        
        $currentPrefix = $prefix . ($isLast ? '└── ' : '├── ');
        $childPrefix = $prefix . ($isLast ? '    ' : '│   ');
        
        // 当前节点
        $typeIcon = $this->getTypeIcon($node['type']);
        $output .= $currentPrefix . $typeIcon . ' ' . $node['file'];
        
        if (isset($node['circular'])) {
            $output .= ' 🔄 (循环依赖)';
        } elseif (isset($node['not_found'])) {
            $output .= ' ❌ (文件不存在)';
        }
        
        $output .= "\n";
        
        // 子节点
        $childCount = count($node['deps']);
        for ($i = 0; $i < $childCount; $i++) {
            $child = $node['deps'][$i];
            $isLastChild = ($i === $childCount - 1);
            $output .= $this->formatTree($child, $childPrefix, $isLastChild);
        }
        
        return $output;
    }
    
    /**
     * 获取文件类型图标
     */
    private function getTypeIcon($type) {
        $icons = array(
            'php' => '🐘',
            'html' => '🌐',
            'htm' => '🌐',
            'js' => '📜',
            'css' => '🎨',
            'twig' => '🍃',
            'blade.php' => '🔪',
        );
        
        return isset($icons[$type]) ? $icons[$type] : '📄';
    }
    
    /**
     * 生成Mermaid格式的依赖图
     */
    public function generateMermaidDiagram() {
        $mermaid = "graph TD\n";
        
        // 添加节点定义
        foreach ($this->dependencies as $file => $deps) {
            $nodeId = $this->getMermaidNodeId($file);
            $typeClass = $deps['type'];
            $mermaid .= "    {$nodeId}[{$file}]\n";
            $mermaid .= "    class {$nodeId} {$typeClass};\n";
        }
        
        // 添加边
        foreach ($this->dependencies as $sourceFile => $deps) {
            $sourceId = $this->getMermaidNodeId($sourceFile);
            
            $allDeps = array_merge(
                $deps['includes'],
                $deps['required'],
                $deps['imports'],
                $deps['links'],
                $deps['scripts']
            );
            
            foreach (array_unique($allDeps) as $targetFile) {
                if (isset($this->dependencies[$targetFile])) {
                    $targetId = $this->getMermaidNodeId($targetFile);
                    $mermaid .= "    {$sourceId} --> {$targetId};\n";
                }
            }
        }
        
        // 添加样式
        $mermaid .= "\n    classDef php fill:#4F5D95,color:#fff\n";
        $mermaid .= "    classDef html fill:#E44D26,color:#fff\n";
        $mermaid .= "    classDef js fill:#F7DF1E,color:#000\n";
        $mermaid .= "    classDef css fill:#1572B6,color:#fff\n";
        
        return $mermaid;
    }
    
    /**
     * 获取Mermaid节点ID
     */
    private function getMermaidNodeId($file) {
        return 'file_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $file);
    }
}

/**
 * 使用示例
 */
if (php_sapi_name() === 'cli') {
    // 命令行模式
    if ($argc > 1) {
        $dir = $argv[1];
        $maxDepth = isset($argv[2]) ? intval($argv[2]) : 3;
        
        try {
            $analyzer = new FileDependencyAnalyzer($dir);
            $graphData = $analyzer->analyzeDirectory($maxDepth);
            
            echo "\n=== 文件依赖分析完成 ===\n\n";
            
            if (isset($argv[3]) && $argv[3] === '--tree') {
                // 生成依赖树
                echo $analyzer->generateDependencyTree();
            } elseif (isset($argv[3]) && $argv[3] === '--mermaid') {
                // 生成Mermaid图
                echo $analyzer->generateMermaidDiagram();
            } else {
                // 生成HTML可视化
                $html = $analyzer->generateVisualization("{$dir} - 文件依赖关系图");
                $outputFile = 'dependency_graph.html';
                file_put_contents($outputFile, $html);
                echo "✅ 可视化图表已生成: {$outputFile}\n";
                echo "📊 包含 " . count($graphData['nodes']) . " 个文件和 " . count($graphData['edges']) . " 个依赖关系\n";
            }
            
        } catch (Exception $e) {
            echo "❌ 错误: " . $e->getMessage() . "\n";
        }
    } else {
        echo "文件依赖关系分析工具\n\n";
        echo "使用方法:\n";
        echo "  php " . basename(__FILE__) . " <目录路径> [最大深度] [选项]\n\n";
        echo "选项:\n";
        echo "  --tree     生成文本依赖树\n";
        echo "  --mermaid  生成Mermaid图\n\n";
        echo "示例:\n";
        echo "  php " . basename(__FILE__) . " ./src 3\n";
        echo "  php " . basename(__FILE__) . " ./src 5 --tree\n";
        echo "  php " . basename(__FILE__) . " ./views 2 --mermaid\n";
    }
} else {
    // 网页模式
    ?>
    <!DOCTYPE html>
    <html lang="zh-CN">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>文件依赖关系分析器</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
            .container { max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
            h1 { color: #333; text-align: center; margin-bottom: 30px; }
            .form-group { margin-bottom: 20px; }
            label { display: block; margin-bottom: 8px; font-weight: bold; color: #555; }
            input, select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 16px; }
            button { background: #4CAF50; color: white; border: none; padding: 12px 20px; border-radius: 5px; cursor: pointer; font-size: 16px; width: 100%; }
            button:hover { background: #45a049; }
            .result { margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 5px; display: none; }
            .loading { display: none; text-align: center; margin: 20px 0; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>📁 文件依赖关系分析器</h1>
            
            <form id="analyzeForm" onsubmit="return analyzeDependencies()">
                <div class="form-group">
                    <label for="directory">项目目录:</label>
                    <input type="text" id="directory" name="directory" value="./" required placeholder="输入项目目录路径">
                </div>
                
                <div class="form-group">
                    <label for="maxDepth">分析深度:</label>
                    <select id="maxDepth" name="maxDepth">
                        <option value="1">1 级</option>
                        <option value="2">2 级</option>
                        <option value="3" selected>3 级</option>
                        <option value="4">4 级</option>
                        <option value="5">5 级</option>
                    </select>
                </div>
                
                <button type="submit">开始分析</button>
            </form>
            
            <div id="loading" class="loading">
                <p>正在分析文件依赖关系，请稍候...</p>
            </div>
            
            <div id="result" class="result">
                <h2>分析完成!</h2>
                <p id="resultText"></p>
                <p><a id="viewLink" href="#" target="_blank">点击查看可视化依赖图</a></p>
            </div>
        </div>
        
        <script>
            function analyzeDependencies() {
                var form = document.getElementById('analyzeForm');
                var resultDiv = document.getElementById('result');
                var loadingDiv = document.getElementById('loading');
                var resultText = document.getElementById('resultText');
                var viewLink = document.getElementById('viewLink');
                
                // 重置显示
                resultDiv.style.display = 'none';
                loadingDiv.style.display = 'block';
                
                var formData = new FormData(form);
                
                fetch('?action=analyze', {
                    method: 'POST',
                    body: formData
                })
                .then(function(response) {
                    return response.json();
                })
                .then(function(data) {
                    loadingDiv.style.display = 'none';
                    
                    if (data.success) {
                        resultText.textContent = '分析完成! 发现 ' + data.fileCount + ' 个文件，' + data.depCount + ' 个依赖关系。';
                        viewLink.href = data.outputFile;
                        resultDiv.style.display = 'block';
                    } else {
                        alert('分析失败: ' + data.error);
                    }
                })
                .catch(function(error) {
                    loadingDiv.style.display = 'none';
                    alert('请求失败: ' + error);
                });
                
                return false;
            }
        </script>
        
        <?php
        if (isset($_GET['action']) && $_GET['action'] === 'analyze' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $dir = isset($_POST['directory']) ? $_POST['directory'] : './';
            $maxDepth = isset($_POST['maxDepth']) ? intval($_POST['maxDepth']) : 3;
            
            try {
                $analyzer = new FileDependencyAnalyzer($dir);
                $graphData = $analyzer->analyzeDirectory($maxDepth);
                
                // 生成HTML文件
                $timestamp = date('Ymd_His');
                $outputFile = "dependency_{$timestamp}.html";
                $html = $analyzer->generateVisualization("{$dir} - 文件依赖关系图");
                file_put_contents($outputFile, $html);
                
                header('Content-Type: application/json');
                echo json_encode(array(
                    'success' => true,
                    'fileCount' => count($graphData['nodes']),
                    'depCount' => count($graphData['edges']),
                    'outputFile' => $outputFile
                ));
                exit;
                
            } catch (Exception $e) {
                header('Content-Type: application/json');
                echo json_encode(array(
                    'success' => false,
                    'error' => $e->getMessage()
                ));
                exit;
            }
        }
        ?>
    </body>
    </html>
    <?php
}
?>