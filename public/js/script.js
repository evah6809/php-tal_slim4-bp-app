
function validateForm() {
    // weight を除外して、血圧などの測定値だけに絞る
    const ids = [
        'm_systolic1', 'm_diastolic1', 'm_pulse1',
        'm_systolic2', 'm_diastolic2', 'm_pulse2',
        'e_systolic1', 'e_diastolic1', 'e_pulse1',
        'e_systolic2', 'e_diastolic2', 'e_pulse2'
    ];

    let hasInput = false;
    for (let id of ids) {
        const el = document.getElementById(id);
        if (el && el.value.trim() !== "") {
            hasInput = true;
            break;
        }
    }

    if (!hasInput) {
        alert("⚠️ 測定値が何も入力されていません。血圧の数値を入力してから保存してください。");
        return false;
    }
    return true;

}

document.addEventListener('DOMContentLoaded', function () {
    const serverToday = new Date().toISOString().split('T')[0];

    const dateInput = document.getElementById('date');
    const submitBtn = document.getElementById('submit-btn');
    const modeBadge = document.getElementById('mode-badge');

    const inputs = {
        weight: document.getElementById('weight'),
        m_sys1: document.getElementById('m_systolic1'),
        m_dia1: document.getElementById('m_diastolic1'),
        m_pulse1: document.getElementById('m_pulse1'),
        m_sys2: document.getElementById('m_systolic2'),
        m_dia2: document.getElementById('m_diastolic2'),
        m_pulse2: document.getElementById('m_pulse2'),
        e_sys1: document.getElementById('e_systolic1'),
        e_dia1: document.getElementById('e_diastolic1'),
        e_pulse1: document.getElementById('e_pulse1'),
        e_sys2: document.getElementById('e_systolic2'),
        e_dia2: document.getElementById('e_diastolic2'),
        e_pulse2: document.getElementById('e_pulse2')
    };

    // 1. 関数の定義
    function loadDailyRecords() {
        console.log("💡 loadDailyRecords が実行されました！日付:", dateInput.value);
        const date = dateInput.value;
        if (!date) return;

        fetch('/?action=get_record&date=' + date)
            .then(response => response.json())
            .then(res => {
                console.log("サーバーからの返却データ:", res);
                Object.values(inputs).forEach(input => input.value = '');

                if (res.status === 'success') {
                    let hasAnyData = false;

                    if (res.data.weight !== null) {
                        inputs.weight.value = res.data.weight;
                    }

                    if (res.data.morning) {
                        hasAnyData = true;
                        inputs.m_sys1.value = res.data.morning.systolic1 || '';
                        inputs.m_dia1.value = res.data.morning.diastolic1 || '';
                        inputs.m_pulse1.value = res.data.morning.pulse1 || '';
                        inputs.m_sys2.value = res.data.morning.systolic2 || '';
                        inputs.m_dia2.value = res.data.morning.diastolic2 || '';
                        inputs.m_pulse2.value = res.data.morning.pulse2 || '';
                    }

                    if (res.data.evening) {
                        hasAnyData = true;
                        inputs.e_sys1.value = res.data.evening.systolic1 || '';
                        inputs.e_dia1.value = res.data.evening.diastolic1 || '';
                        inputs.e_pulse1.value = res.data.evening.pulse1 || '';
                        inputs.e_sys2.value = res.data.evening.systolic2 || '';
                        inputs.e_dia2.value = res.data.evening.diastolic2 || '';
                        inputs.e_pulse2.value = res.data.evening.pulse2 || '';
                    }

                    if (hasAnyData) {
                        submitBtn.innerText = "この内容でデータを修正（上書き保存）する";
                        submitBtn.className = "w-full bg-green-600 hover:bg-green-700 text-white font-bold py-5 px-4 rounded-xl shadow-md text-xl transition";

                        if (date === serverToday) {
                            modeBadge.classList.add('hidden');
                        } else {
                            modeBadge.classList.remove('hidden');
                        }
                    } else {
                        submitBtn.innerText = "この内容で手帳に保存する";
                        submitBtn.className = "w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-5 px-4 rounded-xl shadow-md text-xl transition";
                        modeBadge.classList.add('hidden');
                    }
                }
            })
            .catch(err => console.error("データ読み込み失敗:", err));
    } // ← ★ loadDailyRecords関数はここで終わり！

    // 2. カレンダーの変更イベント設定などの「実行処理」は、関数の「外（ここ）」に書く
    if (dateInput) {
        dateInput.addEventListener('change', loadDailyRecords);
    }

    const urlParams = new URLSearchParams(window.location.search);
    const paramDate = urlParams.get('date');
    if (paramDate && dateInput) {
        dateInput.value = paramDate;
    }

    // 3. ページを開いたときの初回読み込み
    loadDailyRecords();

}); // DOMContentLoaded の終わり
