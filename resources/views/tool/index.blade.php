<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>訂單編號格式化工具</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            margin-bottom: 20px;
        }
        .input-area, .output-area {
            margin-bottom: 20px;
        }
        textarea {
            width: 100%;
            height: 200px;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-family: monospace;
            font-size: 14px;
            box-sizing: border-box;
        }
        button {
            background: #4CAF50;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            margin-right: 10px;
        }
        button:hover {
            background: #45a049;
        }
        .copy-btn {
            background: #2196F3;
        }
        .copy-btn:hover {
            background: #0b7dda;
        }
        .stats {
            margin: 15px 0;
            padding: 10px;
            background: #f0f0f0;
            border-radius: 4px;
            font-size: 14px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #555;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>📋 訂單編號格式化工具</h1>

    <div class="input-area">
        <label>貼上你的資料：</label>
        <textarea id="input" placeholder="請在這裡貼上你的訂單編號..."></textarea>
    </div>

    <button onclick="formatData()">🔄 開始格式化</button>

    <div class="stats" id="stats" style="display: none;"></div>

    <div class="output-area">
        <label>格式化結果：</label>
        <textarea id="output" readonly></textarea>
    </div>

    <button class="copy-btn" onclick="copyResult()">📋 複製結果</button>
</div>

<script>
    function formatData() {
        const input = document.getElementById('input').value;

        if (!input.trim()) {
            alert('請先貼上資料！');
            return;
        }

        // 用換行和逗號分割
        const entries = input.split(/[,\n]+/);

        const formatted = [];
        let alreadyFormatted = 0;
        let newlyFormatted = 0;

        entries.forEach(entry => {
            entry = entry.trim();

            // 跳過空白
            if (!entry) return;

            // 檢查是否已經格式化（有引號）
            if (entry.startsWith("'") && entry.endsWith("'")) {
                formatted.push(entry + ',');
                alreadyFormatted++;
            }
            // 格式化未處理的項目
            else if (entry.startsWith('EUIID')) {
                formatted.push(`'${entry}',`);
                newlyFormatted++;
            }
        });

        // 顯示結果
        document.getElementById('output').value = formatted.join('\n');

        // 顯示統計
        const stats = document.getElementById('stats');
        stats.style.display = 'block';
        stats.innerHTML = `
                ✅ 處理完成！<br>
                總共處理：${formatted.length} 個訂單編號<br>
                已經是正確格式：${alreadyFormatted} 個<br>
                新格式化：${newlyFormatted} 個
            `;
    }

    function copyResult() {
        const output = document.getElementById('output');

        if (!output.value) {
            alert('還沒有結果可以複製！請先格式化資料。');
            return;
        }

        output.select();
        document.execCommand('copy');
        alert('✅ 已複製到剪貼簿！');
    }
</script>
</body>
</html>
