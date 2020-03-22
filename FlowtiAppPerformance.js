var barOptions = {
    responsive: true,            
    legend: { display: false} ,
    title: { display: false }
};
window.onload = function() {
    if(document.getElementById('db-btn')){
        var ctx = document.getElementById('avgexectime').getContext('2d');
        window.barExecTime = new Chart(ctx, {
            type: 'bar',
            data: {
                datasets: [{
                    label: '',
                    barPercentage: 0.5,
                    barThickness: 6,
                    maxBarThickness: 8,
                    minBarLength: 0,
                    backgroundColor: '#6e1970',
                    data: []
                }]
            },
            options: barOptions,
        });
    
        var ctx = document.getElementById('avgmemory').getContext('2d');
        window.barMemory = new Chart(ctx, {
            type: 'bar',
            data: {
                datasets: [{
                    label: '',
                    barPercentage: 0.5,
                    barThickness: 6,
                    maxBarThickness: 8,
                    minBarLength: 0,
                    backgroundColor: '#329e96',
                    data: []
                }]
            },
            options: barOptions,
        });
    }
};

$(document).ready(function(){
    if(document.getElementById('db-btn')){
        $.getJSON('./getChartDataExecTime', function(data){addDataExecTime(data)});
        $.getJSON('./getChartDataMemory', function(data){addDataMemory(data)});
    }
})

function addDataExecTime(data) {
    $(data).each(function(k,v) {
        window.barExecTime.data.labels.push(v.label);
        window.barExecTime.data.datasets.forEach((dataset) => {
            dataset.data.push(v.val);
        });
    });
    window.barExecTime.update();
}
function addDataMemory(data) {
    $(data).each(function(k,v) {
        window.barMemory.data.labels.push(v.label);
        window.barMemory.data.datasets.forEach((dataset) => {
            dataset.data.push(v.val);
        });
    });
    window.barMemory.update();
}

