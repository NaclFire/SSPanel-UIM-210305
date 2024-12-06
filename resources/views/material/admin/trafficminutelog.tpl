{include file='admin/main.tpl'}

<main class="content">
    <div class="content-header ui-content-header">
        <div class="container">
            <h1 class="content-heading">每分钟流量使用记录</h1>
        </div>
    </div>
    <div class="container">
        <div class="col-lg-12 col-sm-12">
            <section class="content-inner margin-top-no">
                <div class="card">
                    <div class="card-main">
                        <div class="card-inner">
                            部分节点不支持流量记录，记录保存2天。
                            <div class="card-row collapse in" id="search_group">
                                <div class="form-group form-group-label">
                                    <label class="floating-label" for="search_by_user">用户ID</label>
                                    <input class="form-control maxwidth-edit" id="search_by_user" type="text">
                                </div>
                                <div class="form-group form-group-label">
                                    <label class="floating-label" for="search_by_node">节点名</label>
                                    <input class="form-control maxwidth-edit" id="search_by_node" type="text">
                                </div>
                                <div class="form-group form-group-label">
                                    <label class="floating-label" for="search_start_time">开始时间</label>
                                    <input class="form-control maxwidth-edit" id="search_start_time" type="text">
                                </div>
                                <div class="form-group form-group-label">
                                    <label class="floating-label" for="search_end_time">结束时间</label>
                                    <input class="form-control maxwidth-edit" id="search_end_time" type="text">
                                </div>
                            </div>
                            <div class="text-right">
                                <a class="btn btn-brand form" id="search">搜索</a>
                            </div>
                            <p>显示表项:
                                {include file='table/checkbox.tpl'}
                            </p>
                        </div>
                    </div>
                </div>
                <div class="table-responsive">
                    {include file='table/table.tpl'}
                </div>
            </section>
        </div>
    </div>
</main>


{include file='admin/footer.tpl'}

<script>
    {include file='table/js_1.tpl'}
    let table_1
    $('#search').on('click', function () {
        initializeDataTable()
    })

    // 定义初始化 DataTable 的函数
    function initializeDataTable() {
        // 销毁已有 DataTable 实例（如果存在）
        if ($.fn.DataTable.isDataTable('#table_1')) {
            $('#table_1').DataTable().destroy();
        }
        table_1 = $('#table_1').DataTable({
            ajax: {
                url: '{$table_config['ajax_url']}',
                type: "POST",
                data: {
                    user: $$getValue('search_by_user'),
                    node: $$getValue('search_by_node'),
                    startTime: $$getValue('search_start_time'),
                    endTime: $$getValue('search_end_time'),
                }
            },
            bFilter: false,
            processing: true,
            serverSide: true,
            order: [[0, 'desc']],
            stateSave: true,
            columnDefs: [
                {
                    targets: [4],
                    render: function (data, type, row) {
                        if (type === 'sort' || type === 'type') {
                            return data;
                        }
                        return formatBytes(data);
                    }
                },
                {
                    targets: ['_all'],
                    className: 'mdl-data-table__cell--non-numeric'
                }
            ],
            columns: [
                {foreach $table_config['total_column'] as $key => $value}
                {
                    "data": "{$key}"
                },
                {/foreach}
            ],
            {include file='table/lang_chinese.tpl'}
        })
    }

    window.addEventListener('load', () => {
        initializeDataTable()
        var has_init = JSON.parse(localStorage.getItem(window.location.href + '-hasinit'));
        if (has_init != true) {
            localStorage.setItem(window.location.href + '-hasinit', true);
        } else {
            {foreach $table_config['total_column'] as $key => $value}
            var checked = JSON.parse(localStorage.getItem(window.location.href + '-haschecked-checkbox_{$key}'));
            if (checked == true) {
                document.getElementById('checkbox_{$key}').checked = true;
            } else {
                document.getElementById('checkbox_{$key}').checked = false;
            }
            {/foreach}
        }

        {foreach $table_config['total_column'] as $key => $value}
        modify_table_visible('checkbox_{$key}', '{$key}');
        {/foreach}
    });

    function formatBytes(bytes) {
        if (bytes === 0) return '0 B';
        const units = ['B', 'KB', 'MB', 'GB', 'TB'];
        const i = Math.floor(Math.log(bytes) / Math.log(1024));
        return (bytes / Math.pow(1024, i)).toFixed(2) + ' ' + units[i];
    }

</script>
