<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>訂單 JSON 產生器</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/PapaParse/5.4.1/papaparse.min.js"></script>
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen p-6">
<div class="max-w-6xl mx-auto">
    <h1 class="text-3xl font-bold text-gray-800 mb-8 text-center">
        訂單 JSON 產生器
    </h1>

    <!-- 模式切換 -->
    <div class="bg-white rounded-lg shadow-lg p-4 mb-6">
        <div class="flex gap-4">
            <button onclick="switchMode('manual')" id="manualBtn" class="flex-1 py-2 px-4 rounded-md font-medium transition-colors bg-blue-600 text-white">
                手動輸入
            </button>
            <button onclick="switchMode('batch')" id="batchBtn" class="flex-1 py-2 px-4 rounded-md font-medium transition-colors bg-gray-200 text-gray-700 hover:bg-gray-300">
                批次上傳
            </button>
        </div>
    </div>

    <!-- 手動輸入模式 -->
    <div id="manualMode" class="grid md:grid-cols-2 gap-6">
        <!-- 輸入表單 -->
        <div class="bg-white rounded-lg shadow-lg p-6">
            <h2 class="text-xl font-semibold text-gray-700 mb-4">輸入資訊</h2>

            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Country</label>
                    <select id="country" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="ID">ID</option>
                        <option value="VN">VN</option>
                        <option value="TH">TH</option>
                        <option value="PH">PH</option>
                        <option value="HK-ID">HK-ID</option>
                        <option value="JP-PH">JP-PH</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">User ID</label>
                    <input type="text" id="userId" placeholder="例如: 56244" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Order ID</label>
                    <input type="text" id="orderId" placeholder="例如: EUIID251110QLYHR" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Amount</label>
                    <input type="number" id="amount" placeholder="例如: 3825" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <div class="pt-2">
                    <p class="text-sm text-gray-600">
                        ✓ Timestamp 會自動產生 (UTC+8)<br/>
                        ✓ MAC 自動填空
                    </p>
                </div>

                <button onclick="sendToApi()" id="sendBtn" class="w-full flex items-center justify-center gap-2 px-4 py-3 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors disabled:bg-gray-400 disabled:cursor-not-allowed font-medium">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                    </svg>
                    <span id="sendBtnText">發送到 API</span>
                </button>
            </div>
        </div>

        <!-- JSON 輸出 -->
        <div class="bg-white rounded-lg shadow-lg p-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-semibold text-gray-700">JSON 輸出</h2>
                <button onclick="copyToClipboard()" class="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                    </svg>
                    <span id="copyBtnText">複製</span>
                </button>
            </div>

            <pre id="jsonOutput" class="bg-gray-900 text-green-400 p-4 rounded-md overflow-auto text-sm font-mono mb-4"></pre>

            <!-- API 回應 -->
            <div id="responseArea"></div>
        </div>
    </div>

    <!-- 批次上傳模式 -->
    <div id="batchMode" class="hidden space-y-6">
        <!-- 檔案上傳區 -->
        <div class="bg-white rounded-lg shadow-lg p-6">
            <h2 class="text-xl font-semibold text-gray-700 mb-4">上傳檔案</h2>
            <div class="border-2 border-dashed border-gray-300 rounded-lg p-8 text-center">
                <svg class="mx-auto mb-4 text-gray-400 w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                </svg>
                <p class="text-gray-600 mb-4">上傳 CSV 或 Excel 檔案 (.csv, .xlsx, .xls)</p>
                <p class="text-sm text-gray-500 mb-4">檔案需包含欄位: country, userId, orderId, amount</p>
                <input type="file" id="fileInput" accept=".csv,.xlsx,.xls" class="hidden" onchange="handleFileUpload(event)">
                <label for="fileInput" class="inline-flex items-center gap-2 px-6 py-3 bg-blue-600 text-white rounded-md hover:bg-blue-700 cursor-pointer transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                    </svg>
                    選擇檔案
                </label>
            </div>
        </div>

        <!-- 資料預覽表格 -->
        <div id="dataPreview" class="hidden bg-white rounded-lg shadow-lg p-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-semibold text-gray-700">
                    資料預覽 (<span id="selectedCount">0</span> / <span id="totalCount">0</span> 已選)
                </h2>
                <div class="flex gap-2">
                    <button onclick="toggleSelectAll()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 transition-colors">
                        <span id="selectAllText">全選</span>
                    </button>
                    <button onclick="sendBatchToApi()" id="batchSendBtn" class="flex items-center gap-2 px-6 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors disabled:bg-gray-400 disabled:cursor-not-allowed">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                        </svg>
                        <span id="batchSendBtnText">發送資料</span>
                    </button>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-100">
                    <tr>
                        <th class="p-2 text-left">選擇</th>
                        <th class="p-2 text-left">Country</th>
                        <th class="p-2 text-left">User ID</th>
                        <th class="p-2 text-left">Order ID</th>
                        <th class="p-2 text-left">Amount</th>
                    </tr>
                    </thead>
                    <tbody id="dataTableBody"></tbody>
                </table>
            </div>
        </div>

        <!-- 批次發送結果 -->
        <div id="batchResults" class="hidden bg-white rounded-lg shadow-lg p-6">
            <h2 class="text-xl font-semibold text-gray-700 mb-4">發送結果</h2>
            <div id="batchResultsList" class="space-y-2"></div>
        </div>
    </div>
</div>

<script>
    let batchData = [];
    let selectedRows = new Set();

    // 更新 JSON 輸出
    function updateJsonOutput() {
        const country = document.getElementById('country').value;
        const userId = document.getElementById('userId').value;
        const orderId = document.getElementById('orderId').value;
        const amount = document.getElementById('amount').value;

        const json = {
            country: country,
            userId: userId,
            orderId: orderId,
            amount: amount ? parseInt(amount) : 0,
            timestamp: Math.floor(Date.now() / 1000),
            mac: ""
        };

        document.getElementById('jsonOutput').textContent = JSON.stringify(json, null, 4);
    }

    // 監聽輸入變化
    ['country', 'userId', 'orderId', 'amount'].forEach(id => {
        document.getElementById(id).addEventListener('input', updateJsonOutput);
    });

    // 初始化 JSON 輸出
    updateJsonOutput();

    // 複製到剪貼簿
    function copyToClipboard() {
        const text = document.getElementById('jsonOutput').textContent;
        navigator.clipboard.writeText(text);
        document.getElementById('copyBtnText').textContent = '已複製';
        setTimeout(() => {
            document.getElementById('copyBtnText').textContent = '複製';
        }, 2000);
    }

    // 發送到 API
    async function sendToApi() {
        const userId = document.getElementById('userId').value;
        const orderId = document.getElementById('orderId').value;
        const amount = document.getElementById('amount').value;

        if (!userId || !orderId || !amount) {
            alert('請填寫所有必填欄位');
            return;
        }

        const sendBtn = document.getElementById('sendBtn');
        const sendBtnText = document.getElementById('sendBtnText');
        sendBtn.disabled = true;
        sendBtnText.textContent = '發送中...';

        const payload =JSON.stringify({
            country: document.getElementById('country').value,
            userId: userId,
            orderId: orderId,
            amount: parseInt(amount),
            timestamp: Math.floor(Date.now() / 1000),
            mac: ""
        });
        console.log(payload);


        try {
            const response = await fetch('https://event.eui.money/api/v1/payment_finish', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                },
                body: payload,
            });

            const data = await response.json();

            const responseArea = document.getElementById('responseArea');
            const bgColor = response.ok ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200';
            const textColor = response.ok ? 'text-green-800' : 'text-red-800';
            const icon = response.ok ? '✓' : '✗';
            const title = response.ok ? '發送成功' : '發送失敗';

            responseArea.innerHTML = `
                    <div class="mt-4 p-4 rounded-md border ${bgColor}">
                        <h3 class="font-semibold mb-2 ${textColor}">${icon} ${title}</h3>
                        <div class="text-sm">
                            <p class="mb-1">Status: ${response.status}</p>
                            <pre class="bg-white p-2 rounded text-xs overflow-auto">${JSON.stringify(data, null, 2)}</pre>
                        </div>
                    </div>
                `;
        } catch (error) {
            document.getElementById('responseArea').innerHTML = `
                    <div class="mt-4 p-4 rounded-md border bg-red-50 border-red-200">
                        <h3 class="font-semibold mb-2 text-red-800">✗ 發送失敗</h3>
                        <div class="text-sm">
                            <p class="mb-1">Error: ${error.message}</p>
                        </div>
                    </div>
                `;
        } finally {
            sendBtn.disabled = false;
            sendBtnText.textContent = '發送到 API';
        }
    }

    // 切換模式
    function switchMode(mode) {
        const manualMode = document.getElementById('manualMode');
        const batchMode = document.getElementById('batchMode');
        const manualBtn = document.getElementById('manualBtn');
        const batchBtn = document.getElementById('batchBtn');

        if (mode === 'manual') {
            manualMode.classList.remove('hidden');
            batchMode.classList.add('hidden');
            manualBtn.classList.remove('bg-gray-200', 'text-gray-700', 'hover:bg-gray-300');
            manualBtn.classList.add('bg-blue-600', 'text-white');
            batchBtn.classList.remove('bg-blue-600', 'text-white');
            batchBtn.classList.add('bg-gray-200', 'text-gray-700', 'hover:bg-gray-300');
        } else {
            manualMode.classList.add('hidden');
            batchMode.classList.remove('hidden');
            batchBtn.classList.remove('bg-gray-200', 'text-gray-700', 'hover:bg-gray-300');
            batchBtn.classList.add('bg-blue-600', 'text-white');
            manualBtn.classList.remove('bg-blue-600', 'text-white');
            manualBtn.classList.add('bg-gray-200', 'text-gray-700', 'hover:bg-gray-300');
        }
    }

    // 處理檔案上傳
    function handleFileUpload(event) {
        const file = event.target.files[0];
        if (!file) return;

        const fileExtension = file.name.split('.').pop().toLowerCase();

        if (fileExtension === 'csv') {
            Papa.parse(file, {
                header: true,
                skipEmptyLines: true,
                complete: function(results) {
                    processParsedData(results.data);
                }
            });
        } else if (['xlsx', 'xls'].includes(fileExtension)) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const data = new Uint8Array(e.target.result);
                const workbook = XLSX.read(data, { type: 'array' });
                const firstSheet = workbook.Sheets[workbook.SheetNames[0]];
                const jsonData = XLSX.utils.sheet_to_json(firstSheet);
                processParsedData(jsonData);
            };
            reader.readAsArrayBuffer(file);
        }
    }

    // 處理解析後的資料
    function processParsedData(data) {
        batchData = data.map((row, index) => ({
            id: index,
            country: row.country || row.Country || 'ID',
            userId: row.userId || row.UserId || row.user_id || '',
            orderId: row.orderId || row.OrderId || row.order_id || '',
            amount: row.amount || row.Amount || 0
        })).filter(row => row.userId && row.orderId);

        selectedRows = new Set(batchData.map(row => row.id));
        renderDataTable();
        updateSelectedCount();
        document.getElementById('dataPreview').classList.remove('hidden');
    }

    // 渲染資料表格
    function renderDataTable() {
        const tbody = document.getElementById('dataTableBody');
        tbody.innerHTML = '';

        batchData.forEach(row => {
            const tr = document.createElement('tr');
            tr.className = 'border-b hover:bg-gray-50';
            tr.innerHTML = `
                    <td class="p-2">
                        <input type="checkbox" ${selectedRows.has(row.id) ? 'checked' : ''} onchange="toggleRow(${row.id})" class="w-4 h-4">
                    </td>
                    <td class="p-2">${row.country}</td>
                    <td class="p-2">${row.userId}</td>
                    <td class="p-2">${row.orderId}</td>
                    <td class="p-2">${row.amount}</td>
                `;
            tbody.appendChild(tr);
        });

        document.getElementById('totalCount').textContent = batchData.length;
    }

    // 切換單行選擇
    function toggleRow(id) {
        if (selectedRows.has(id)) {
            selectedRows.delete(id);
        } else {
            selectedRows.add(id);
        }
        updateSelectedCount();
    }

    // 全選/取消全選
    function toggleSelectAll() {
        if (selectedRows.size === batchData.length) {
            selectedRows.clear();
        } else {
            selectedRows = new Set(batchData.map(row => row.id));
        }
        renderDataTable();
        updateSelectedCount();
    }

    // 更新選擇數量
    function updateSelectedCount() {
        document.getElementById('selectedCount').textContent = selectedRows.size;
        document.getElementById('selectAllText').textContent =
            selectedRows.size === batchData.length ? '取消全選' : '全選';
        document.getElementById('batchSendBtnText').textContent = `發送 ${selectedRows.size} 筆資料`;
    }

    // 批次發送到 API
    async function sendBatchToApi() {
        if (selectedRows.size === 0) {
            alert('請至少選擇一筆資料');
            return;
        }

        const batchSendBtn = document.getElementById('batchSendBtn');
        const batchSendBtnText = document.getElementById('batchSendBtnText');
        batchSendBtn.disabled = true;
        batchSendBtnText.textContent = '發送中...';

        const selectedData = batchData.filter(row => selectedRows.has(row.id));
        const results = [];

        for (const row of selectedData) {

            const now = new Date();

// 格式化年月日時分秒
            const y = now.getFullYear();
            const M = String(now.getMonth() + 1).padStart(2, '0');
            const d = String(now.getDate()).padStart(2, '0');
            const h = String(now.getHours()).padStart(2, '0');
            const m = String(now.getMinutes()).padStart(2, '0');
            const s = String(now.getSeconds()).padStart(2, '0');

// 取毫秒（3 位）+ 隨機數（再補 5 位數）
            const ms = String(now.getMilliseconds()).padStart(3, '0');
            const rand = String(Math.floor(Math.random() * 100000)).padStart(5, '0');

// 組合成字串
            const timestamp = `${y}${M}${d}${h}${m}${s}${ms}${rand}`;
            const payload = {
                country: row.country,
                userId: row.userId,
                orderId: row.orderId,
                amount: parseInt(row.amount),
                timestamp: Math.floor(Date.now() / 1000),
                mac: ""
            };



            try {
                const response = await fetch('https://event.eui.money/api/v1/payment_finish', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                    },
                    body: payload
                });

                const data = await response.json();
                results.push({
                    orderId: row.orderId,
                    success: response.ok,
                    status: response.status,
                    data: data
                });
            } catch (error) {
                results.push({
                    orderId: row.orderId,
                    success: false,
                    status: 0,
                    error: error.message
                });
            }
        }

        displayBatchResults(results);
        batchSendBtn.disabled = false;
        batchSendBtnText.textContent = `發送 ${selectedRows.size} 筆資料`;
    }

    // 顯示批次發送結果
    function displayBatchResults(results) {
        const batchResultsList = document.getElementById('batchResultsList');
        batchResultsList.innerHTML = '';

        results.forEach(result => {
            const bgColor = result.success ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200';
            const icon = result.success ? '✓' : '✗';

            const div = document.createElement('div');
            div.className = `p-3 rounded-md border ${bgColor}`;
            div.innerHTML = `
                    <div class="flex justify-between items-start">
                        <span class="font-medium">${icon} Order ID: ${result.orderId}</span>
                        <span class="text-sm">Status: ${result.status}</span>
                    </div>
                    ${!result.success ? `<p class="text-sm text-red-600 mt-1">${result.error || JSON.stringify(result.data)}</p>` : ''}
                `;
            batchResultsList.appendChild(div);
        });

        document.getElementById('batchResults').classList.remove('hidden');
    }
</script>
</body>
</html>
