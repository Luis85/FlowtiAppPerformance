window.onload = function() {
    if(document.getElementById('avgexectimechart')){
        var ctx = document.getElementById('avgtime').getContext('2d');
        window.avgtime = new Chart(ctx, {
            type: 'bar',
            data: {
                datasets: [{
                    label: false,
                    backgroundColor: '#6e1970',
                    data: getAvgChartData(ctx.canvas.id)
                }]
            },
            options: barOptions,
        });
    }
    if(document.getElementById('avgmemorychart')){
        var ctx = document.getElementById('avgmemory').getContext('2d');
        window.avgmemory = new Chart(ctx, {
            type: 'bar',
            data: {
                datasets: [{
                    label: false,
                    backgroundColor: '#329e96',
                    data: getAvgChartData(ctx.canvas.id)
                }]
            },
            options: barOptions,
        });
    }
};

var barOptions = {
    responsive: true,
    legend: { display: false},
    title: { display: false }
};
function getAvgChartData(chart){
    $.getJSON('./getAvgChartData?data='+chart, function(res){
        chart = window[chart];
        $(res).each(function(k,v) {
            chart.data.labels.push(v.label);
            chart.data.datasets.forEach((dataset) => {
                dataset.data.push(v.val);
            });
        });
        chart.update();
    });
}