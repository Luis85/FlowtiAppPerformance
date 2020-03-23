<a class="uk-button uk-button-default uk-button-small" href="./">Back</a> <a class="uk-float-right  uk-button-small uk-button uk-button-danger" href="./clearlogs">Clear Profiles</a>
<hr />
<div class="uk-card uk-card-default uk-card-body">

    <ul class="uk-subnav uk-subnav-pill" uk-margin>
        <?php foreach($tableModules as $module) : ?>
            <li class="filter-option">
                <a data-cat="<?= $module->title ?>" class="module-filter" href="#"><?= $module->title ?></a>
            </li>
        <?php endforeach ?>
        <li style="display:none" class="uk-active module-filter-reset">
            <a href="#">Reset Filter</a>
        </li>
    </ul>
    
    <table id="table" class="uk-table uk-table-divider uk-table-hover uk-table-small">
        <thead>
            <tr>
                <th>Module</th>
                <th>Method</th>
                <th>Page</th>
                <th class="memavg">Memory<br />&#216; <span></span> MB</th>
                <th class="timeavg">Time<br />&#216; <span></span> MS</th>
                <th class="loadavg">SysLoad<br />&#216; <span></span> %</th>
            </tr>
        </thead>
        <tfoot>
            <tr>
                <th></th>
                <th></th>
                <th></th>
                <th class="memavg">&#216; <span></span> MB</th>
                <th class="timeavg">&#216; <span></span> MS</th>
                <th class="loadavg">&#216; <span></span> %</th>
            </tr>
        </tfoot>
    </table>
</div>


<script>
$(document).ready( function () {
    var options = {
        processing: true,
        serverSide: true,
        ordering: false,
        ajax: {
           url: './getDatatable',
           type: 'POST'
        },
        columns: [
            { data: 'module' },
            { data: 'method' },
            { data: 'page' },
            { data: 'memory' },
            { data: 'time' },
            { data: 'sysload' }
        ]
    };
    var table = $('#table').DataTable(options);

    $('.module-filter').on('click', function(e) {
        e.preventDefault();
        $('.filter-option').removeClass('uk-active');
        $(this).parent().addClass('uk-active');
        $('.module-filter-reset').fadeIn();
        cat = $(this).data('cat');
        table.ajax.url( './getDatatable?filter='+cat ).load();
    } );
    $('.module-filter-reset').on('click', function(e) {
        e.preventDefault();
        $('.filter-option').removeClass('uk-active');
        $(this).fadeOut();
        table.ajax.url( './getDatatable' ).load();
    } );
    $('table.dataTable').on('click','.cell-filter', function(e){
        var text = $(this).text();
        $('.filter-option').removeClass('uk-active');
        $('.module-filter-reset').fadeIn();
        table.ajax.url( './getDatatable?filter='+text ).load();
    });

    table.on('xhr.dt', function ( e, settings, json, xhr ) {
        $('.memavg span').text(json.avgMem);
        $('.timeavg span').text(json.avgTime);
        $('.loadavg span').text(json.avgLoad);
    })

} );


</script>