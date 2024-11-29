{include file='admin/main.tpl'}

<main class="content">
    <div class="content-header ui-content-header">
        <div class="container">
            <h1 class="content-heading">用户列表</h1>
        </div>
    </div>
    <div class="container">
        <div class="col-lg-12 col-sm-12">
            <section class="content-inner margin-top-no">
                <div class="card">
                    <div class="card-main">
                        <div class="card-inner">
                            <p>系统中所有用户的列表。</p>
                            {if $user->isAdmin()}
                                <p>
                                <div class="checkbox switch">
                                    <label for="is_admin">
                                        <input class="access-hide"
                                               id="is_admin" type="checkbox"><span class="switch-toggle"></span>只看管理员
                                    </label>
                                </div>
                                <br>
                                <div class="checkbox switch">
                                    <label for="is_salesman">
                                        <input class="access-hide"
                                               id="is_salesman" type="checkbox"><span class="switch-toggle"></span>只看代理
                                    </label>
                                </div>
                                </p>
                            {/if}
                            <p>
                                付费用户：{$user->paidUserCount()}
                            </p>
                            <p>显示表项:
                                {include file='table/checkbox.tpl'}
                            </p>
                        </div>
                    </div>
                </div>
                <div class="card">
                    <div class="card-main">
                        <div class="card-inner">
                            <div class="form-group form-group-label">
                                <label class="floating-label" for="quick_create"> 输入 email 快速创建新用户 </label>
                                <input class="form-control maxwidth-edit" id="quick_create" type="text">
                            </div>
                        </div>
                        <div class="card-inner">
                            <div class="form-group form-group-label">
                                <label for="new_user_add_shop">
                                    <label class="floating-label" for="new_user_add_shop"> 是否添加套餐 </label>
                                    <select id="new_user_add_shop" class="form-control maxwidth-edit">
                                        <option value="0">不添加</option>
                                        {foreach $shops as $shop}
                                            <option value="{$shop->id}">{$shop->name}</option>
                                        {/foreach}
                                    </select>
                                </label>
                            </div>
                        </div>
                        {if $user->isAdmin()}
                            <div class="card-inner">
                                <div class="form-group form-group-label">
                                    <label class="floating-label" for="new_user_money">
                                        用户余额「-1为按默认设置，其他为指定值」 </label>
                                    <input class="form-control maxwidth-edit" id="new_user_money" type="text"
                                           value="-1">
                                </div>
                            </div>
                        {/if}
                        <div class="card-action">
                            <div class="card-action-btn pull-left">
                                <a class="btn btn-flat waves-attach waves-light" id="quick_create_confirm"><span
                                            class="icon">check</span>&nbsp;创建</a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="table-responsive">
                    {include file='table/table.tpl'}
                </div>
                <div aria-hidden="true" class="modal modal-va-middle fade" id="operate_modal" role="dialog"
                     tabindex="-1">
                    <div class="modal-dialog modal-xs">
                        <div class="modal-content">
                            <div class="modal-heading">
                                <a class="modal-close" data-dismiss="modal">×</a>
                                <h2 class="modal-title">请选择操作</h2>
                            </div>
                            <div class="modal-inner">
                                <a class="btn btn-brand" id="edit">编辑</a>
                                <a class="btn btn-brand" id="changetouser" href="javascript:void(0);"
                                   onClick="changetouser_modal_show()">切换为该用户</a>
                                <a class="btn btn-brand" id="renew" href="javascript:void(0);"
                                   onClick="renew_modal_show()">续费</a>
                                <a class="btn btn-brand" id="copylink" href="javascript:void(0);"
                                   onClick="copylink_modal_show()">复制订阅地址</a>
                                <a class="btn btn-brand-accent btn-margin-top-bottom" id="delete"
                                   href="javascript:void(0);"
                                   onClick="delete_modal_show()">删除</a>
                            </div>
                            <div class="modal-footer">
                                <p class="text-right">
                                    <button class="btn btn-flat btn-brand-accent waves-attach waves-effect"
                                            data-dismiss="modal" type="button">关闭
                                    </button>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                <div aria-hidden="true" class="modal modal-va-middle fade" id="delete_modal" role="dialog"
                     tabindex="-1">
                    <div class="modal-dialog modal-xs">
                        <div class="modal-content">
                            <div class="modal-heading">
                                <a class="modal-close" data-dismiss="modal">×</a>
                                <h2 class="modal-title">确认要删除？</h2>
                            </div>
                            <div class="modal-inner">
                                <p>请您确认。</p>
                            </div>
                            <div class="modal-footer">
                                <p class="text-right">
                                    <button class="btn btn-flat btn-brand-accent waves-attach waves-effect"
                                            data-dismiss="modal" type="button">取消
                                    </button>
                                    <button class="btn btn-flat btn-brand-accent waves-attach" data-dismiss="modal"
                                            id="delete_input" type="button">确定
                                    </button>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                <div aria-hidden="true" class="modal modal-va-middle fade" id="changetouser_modal" role="dialog"
                     tabindex="-1">
                    <div class="modal-dialog modal-xs">
                        <div class="modal-content">
                            <div class="modal-heading">
                                <a class="modal-close" data-dismiss="modal">×</a>
                                <h2 class="modal-title">确认要切换为该用户？</h2>
                            </div>
                            <div class="modal-inner">
                                <p>请您确认。</p>
                            </div>
                            <div class="modal-footer">
                                <p class="text-right">
                                    <button class="btn btn-flat btn-brand-accent waves-attach waves-effect"
                                            data-dismiss="modal" type="button">取消
                                    </button>
                                    <button class="btn btn-flat btn-brand-accent waves-attach" data-dismiss="modal"
                                            id="changetouser_input" type="button">确定
                                    </button>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                <div aria-hidden="true" class="modal modal-va-middle fade" id="renew_modal" role="dialog"
                     tabindex="-1">
                    <div class="modal-dialog modal-xs">
                        <div class="modal-content">
                            <div class="modal-heading">
                                <a class="modal-close" data-dismiss="modal">×</a>
                                <h2 class="modal-title">请选择续费套餐</h2>
                            </div>
                            <div class="modal-inner">
                                {foreach $shops as $shop}
                                    <a class="btn btn-brand btn-margin-top-bottom" id="renew{$shop->id}"
                                       href="javascript:void(0);"
                                       onClick="userRenew({$shop->id})">{$shop->name}</a>
                                {/foreach}
                            </div>
                            <div class="modal-footer">
                                <p class="text-right">
                                    <button class="btn btn-flat btn-brand-accent waves-attach waves-effect"
                                            data-dismiss="modal" type="button">关闭
                                    </button>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                <div aria-hidden="true" class="modal modal-va-middle fade" id="copylink_modal" role="dialog"
                     tabindex="-1">
                    <div class="modal-dialog modal-xs">
                        <div class="modal-content">
                            <div class="modal-heading">
                                <a class="modal-close" data-dismiss="modal">×</a>
                                <h2 class="modal-title">请选择订阅地址类型</h2>
                            </div>
                            <div class="modal-inner">
                                <a class="btn btn-brand copy-text" id="v2ray_link"
                                   href="javascript:void(0);">V2Ray</a>
                                <a class="btn btn-brand copy-text" id="clash_link"
                                   href="javascript:void(0);">Clash</a>
                            </div>
                            <div class="modal-footer">
                                <p class="text-right">
                                    <button class="btn btn-flat btn-brand-accent waves-attach waves-effect"
                                            data-dismiss="modal" type="button">关闭
                                    </button>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                {include file='dialog.tpl'}
        </div>
    </div>
</main>

{include file='admin/footer.tpl'}

<script>
    var operaUserId;

    function copylink_modal_show() {
        $("#copylink_modal").modal();
    }

    function renew_modal_show() {
        $("#renew_modal").modal();
    }

    function operate_modal_show(id) {
        operaUserId = id;
        getUserLink()
        var editUser = "/admin/user/" + operaUserId + "/edit";
        $("#edit").attr("href", editUser);
        $("#operate_modal").modal();
    }

    function delete_modal_show() {
        $("#delete_modal").modal();
    }

    function changetouser_modal_show() {
        $("#changetouser_modal").modal();
    }

    function userRenew(shopId) {
        $.ajax({
            type: 'post',
            url: '/admin/user/buy',
            dataType: 'json',
            data: {
                shopId: shopId,
                disableothers: 1,
                autorenew: 0,
                userId: operaUserId
            },
            success: data => {
                $("#result").modal();
                $$.getElementById('msg').innerHTML = data.msg;
                window.setTimeout("location.href='/admin/user'", 3000);
            },
            error: jqXHR => {
                $("#result").modal();
                $$.getElementById('msg').innerHTML = `${ldelim}jqXHR{rdelim} 发生了错误。`;
            }
        })
    }

    function getUserLink() {
        $.ajax({
            type: 'post',
            url: '/admin/user/link',
            dataType: 'json',
            data: {
                id: operaUserId,
            },
            success: data => {
                if (data.ret) {
                    $("#v2ray_link").attr("data-clipboard-text", data.link + "?sub=3")
                    $("#clash_link").attr("data-clipboard-text", data.link + "?clash=1")
                }
            }
        });
    }

    $("#v2ray_link").click(function () {
        var link = $(this).attr('data-clipboard-text')
        if (link) {
            navigator.clipboard.writeText(link).then(function () {
                // 复制成功
                $("#result").modal();
                $$.getElementById('msg').innerHTML = '已复制，请您继续接下来的操作';
            }).catch(function (error) {
                // 复制失败
                $("#result").modal();
                $$.getElementById('msg').innerHTML = '复制失败：' + error;
            });
        } else {
            // 复制失败
            $("#result").modal();
            $$.getElementById('msg').innerHTML = '复制失败：请再复制一次';
        }


    })
    $("#clash_link").click(function () {
        var link = $(this).attr('data-clipboard-text')
        if (link) {
            navigator.clipboard.writeText(link).then(function () {
                // 复制成功
                $("#result").modal();
                $$.getElementById('msg').innerHTML = '已复制，请您继续接下来的操作';
            }).catch(function (error) {
                // 复制失败
                $("#result").modal();
                $$.getElementById('msg').innerHTML = '复制失败：' + error;
            });
        } else {
            // 复制失败
            $("#result").modal();
            $$.getElementById('msg').innerHTML = '复制失败：请再复制一次';
        }
    })
    // 监听复选框状态变化
    if (document.getElementById('is_admin')) {
        document.getElementById('is_admin').addEventListener('change', initializeDataTable);
    }
    if (document.getElementById('is_salesman')) {
        document.getElementById('is_salesman').addEventListener('change', initializeDataTable);
    }

    // 定义初始化 DataTable 的函数
    function initializeDataTable() {
        let is_admin = 0;
        if (document.getElementById('is_admin')) {
            is_admin = document.getElementById('is_admin')?.checked ? 1 : 0;
        }
        let is_salesman = 0
        if (document.getElementById('is_salesman')) {
            is_salesman = document.getElementById('is_salesman')?.checked ? 1 : 0;
        }

        // 销毁已有 DataTable 实例（如果存在）
        if ($.fn.DataTable.isDataTable('#table_1')) {
            $('#table_1').DataTable().destroy();
        }

        // 重新初始化 DataTable
        table_1 = $('#table_1').DataTable({
            order: [[1, 'asc']],
            stateSave: true,
            serverSide: true,
            ajax: {
                url: "/admin/user/ajax",
                type: "POST",
                data: {
                    onlyAdmin: is_admin,
                    onlySalesman: is_salesman
                }
            },
            columns: [
                {literal}
                {"data": "op", "orderable": false},
                {"data": "querys"},
                {"data": "id"},
                {"data": "user_name"},
                {"data": "remark"},
                {"data": "email"},
                {"data": "money"},
                {"data": "im_type"},
                {"data": "im_value"},
                {"data": "node_group"},
                {"data": "expire_in"},
                {"data": "class"},
                {"data": "class_expire"},
                {"data": "passwd"},
                {"data": "port"},
                {"data": "method"},
                {"data": "protocol"},
                {"data": "obfs"},
                {"data": "obfs_param"},
                {"data": "online_ip_count", "orderable": false},
                {"data": "last_ss_time", "orderable": false},
                {"data": "used_traffic"},
                {"data": "enable_traffic"},
                {"data": "last_checkin_time", "orderable": false},
                {"data": "today_traffic"},
                {"data": "enable"},
                {"data": "reg_date"},
                {"data": "reg_ip"},
                {"data": "auto_reset_day"},
                {"data": "auto_reset_bandwidth"},
                {"data": "ref_by"},
                {"data": "ref_by_user_name", "orderable": false},
                {"data": "top_up", "orderable": false}
                {/literal}
            ],
            columnDefs: [
                {
                    targets: ['_all'],
                    className: 'mdl-data-table__cell--non-numeric'
                }
            ],
            {include file='table/lang_chinese.tpl'}
        });
    }
    {include file='table/js_1.tpl'}
    window.addEventListener('load', () => {
        initializeDataTable();
        var has_init = JSON.parse(localStorage.getItem(`${ldelim}window.location.href{rdelim}-hasinit`));
        if (has_init !== true) {
            localStorage.setItem(`${ldelim}window.location.href{rdelim}-hasinit`, true);
        } else {
            {foreach $table_config['total_column'] as $key => $value}
            var checked = JSON.parse(localStorage.getItem(window.location.href + '-haschecked-checkbox_{$key}'));
            if (checked) {
                $$.getElementById('checkbox_{$key}').checked = true;
            } else {
                $$.getElementById('checkbox_{$key}').checked = false;
            }
            {/foreach}
        }
        {foreach $table_config['total_column'] as $key => $value}
        modify_table_visible('checkbox_{$key}', '{$key}');
        {/foreach}
        function delete_id() {
            $.ajax({
                type: "DELETE",
                url: "/admin/user",
                dataType: "json",
                data: {
                    id: operaUserId
                },
                success: data => {
                    if (data.ret) {
                        $("#result").modal();
                        $$.getElementById('msg').innerHTML = data.msg;
                        window.setTimeout("location.href='/admin/user'", 5000);
                        {include file='table/js_delete.tpl'}
                    } else {
                        $("#result").modal();
                        $$.getElementById('msg').innerHTML = data.msg;
                    }
                },
                error: jqXHR => {
                    $("#result").modal();
                    $$.getElementById('msg').innerHTML = `${ldelim}jqXHR{rdelim} 发生了错误。`;
                }
            });
        }

        $$.getElementById('delete_input').addEventListener('click', delete_id);
        // $$.getElementById('search_button').addEventListener('click', () => {
        //     if ($$.getElementById('search') !== '') search();
        // });
        function changetouser_id() {
            $.ajax({
                type: "POST",
                url: "/admin/user/changetouser",
                dataType: "json",
                data: {
                    userid: operaUserId,
                    adminid: {$user->id},
                    local: '/admin/user'
                },
                success: data => {
                    if (data.ret) {
                        $("#result").modal();
                        $$.getElementById('msg').innerHTML = data.msg;
                        window.setTimeout("location.href='/user'", {$config['jump_delay']});
                    } else {
                        $("#result").modal();
                        $$.getElementById('msg').innerHTML = data.msg;
                    }
                },
                error: jqXHR => {
                    $("#result").modal();
                    $$.getElementById('msg').innerHTML = `${ldelim}jqXHR{rdelim} 发生了错误。`;
                }
            });
        }

        $$.getElementById('changetouser_input').addEventListener('click', changetouser_id);

        function quickCreate() {
            $.ajax({
                type: 'POST',
                url: '/admin/user/create',
                dataType: 'json',
                data: {
                    userEmail: $$getValue('quick_create'),
                    userMoney: (document.getElementById('new_user_money')) ? $$getValue('new_user_money') : -1,
                    userShop: $$getValue('new_user_add_shop')
                },
                success: data => {
                    $("#result").modal();
                    $$.getElementById('msg').innerHTML = data.msg;
                    window.setTimeout("location.href='/admin/user'", 5000);
                },
                error: jqXHR => {
                    $("#result").modal();
                    $$.getElementById('msg').innerHTML = `${ldelim}jqXHR{rdelim} 发生了错误。`;
                }
            })
        }

        $$.getElementById('quick_create_confirm').addEventListener('click', quickCreate)
    })
</script>
