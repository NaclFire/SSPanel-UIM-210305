{include file='admin/main.tpl'}

<main class="content">
    <div class="content-header ui-content-header">
        <div class="container">
            <h1 class="content-heading">编辑节点 #{$node->id}</h1>
        </div>
    </div>
    <div class="container">
        <div class="col-lg-12 col-sm-12">
            <section class="content-inner margin-top-no">
                <form id="main_form">
                    <div class="card">
                        <div class="card-main">
                            <div class="card-inner">
                                <div class="form-group form-group-label">
                                    <label class="floating-label" for="name">节点名称</label>
                                    <input class="form-control maxwidth-edit" id="name" name="name" type="text"
                                           value="{$node->name}">
                                </div>
                                <div class="form-group form-group-label">
                                    <label class="floating-label" for="server">节点地址</label>
                                    <input class="form-control maxwidth-edit" id="server" name="server" type="text"
                                           value="{$node->server}">
                                    <p class="form-control-guide"><i class="material-icons">info</i>SS节点格式：ip;端口;server=中转域名|host=节点域名|outside_port=中转端口
                                    </p>
                                    <p class="form-control-guide"><i class="material-icons">info</i>v2ray节点格式：ip;端口;0;(tcp或ws);(tls或reality);server=中转域名|host=节点域名|outside_port=中转端口
                                    </p>
                                    <p class="form-control-guide"><i class="material-icons">info</i>v2ray节点启用VLESS：在协议配置后增加：|enable_vless=true
                                    </p>
                                    <p class="form-control-guide"><i class="material-icons">info</i>v2ray节点流控flow：在协议配置后增加：|flow=flow-vlaue
                                    </p>
                                </div>
                                <div class="form-group form-group-label">
                                    <label class="floating-label" for="server">节点IP</label>
                                    <input class="form-control maxwidth-edit" id="node_ip" name="node_ip" type="text" value="{$node->node_ip}">
                                    <p class="form-control-guide"><i class="material-icons">info</i>支持多个ip对接同一个节点，多个以“;”分隔，双栈机器以#分隔。
                                    </p>
                                    <p class="form-control-guide"><i class="material-icons">info</i>例：1.1.1.1#2409:8c54:80:120:0:1:0:5b:1.1.1.2#2409:8c54:80:120:0:1:0:5c
                                    </p>
                                </div>
                                <div class="form-group form-group-label">
                                    <div class="form-group form-group-label">
                                        <label class="floating-label" for="sort">节点类型</label>
                                        <select id="sort" class="form-control maxwidth-edit" name="sort">
                                            <option value="0" {if $node->sort==0}selected{/if}>Shadowsocks</option>
                                            <option value="1" {if $node->sort==1}selected{/if}>AnyTLS</option>
                                            <option value="11" {if $node->sort==11||$node->sort==12}selected{/if}>V2Ray</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="form-group form-group-label" id="vless_switch_group" style="display:none;">
                                    <div class="checkbox switch">
                                        <label for="enable_vless">
                                            <input class="access-hide" id="enable_vless" type="checkbox">
                                            <span class="switch-toggle"></span>
                                            启用 VLESS
                                        </label>
                                    </div>

                                    <div class="form-group form-group-label" id="vless_group" style="display:none;">
                                        <div class="form-group form-group-label" id="security_group">
                                            <label class="floating-label" for="security_select">安全性</label>
                                            <select id="security_select" class="form-control maxwidth-edit">
                                                <option value="">无</option>
                                                <option value="TLS">TLS</option>
                                                <option value="Reality">Reality</option>
                                            </select>
                                        </div>
                                        <div class="form-group form-group-label" id="reality_option_group" style="display:none;">
                                            <div class="form-group form-group-label">
                                                <label class="floating-label" for="server">伪装站点:端口</label>
                                                <input class="form-control maxwidth-edit" id="dest" name="dest" type="text" value={Tool::parseJSON($node->method,'dest')}>
                                            </div>
                                            <a class="btn btn-brand" id="generate_key">生成密钥</a>
                                            <div class="form-group form-group-label">
                                                <label class="floating-label" for="server">私钥</label>
                                                <input class="form-control maxwidth-edit" id="private_key" name="private_key" type="text" value={Tool::parseJSON($node->method,'private_key')}>
                                            </div>
                                            <div class="form-group form-group-label">
                                                <label class="floating-label" for="server">公钥</label>
                                                <input class="form-control maxwidth-edit" id="public_key" name="public_key" type="text" value={Tool::parseJSON($node->method,'public_key')}>
                                            </div>
                                        </div>
                                        <div class="form-group form-group-label" id="flow_group">
                                            <label class="floating-label" for="flow_select">流控</label>
                                            <select id="flow_select" class="form-control maxwidth-edit">
                                                <option value="">不使用</option>
                                                <option value="xtls-rprx-vision">xtls-rprx-vision</option>
                                                <option value="xtls-rprx-direct">xtls-rprx-direct</option>
                                                <option value="xtls-rprx-splice">xtls-rprx-splice</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group form-group-label" id="method_group" style="display:none;">
                                    <label class="floating-label" for="method">加密方式</label>
                                    <select id="method" name="method" class="form-control maxwidth-edit">
                                        <option value="2022-blake3-chacha20-poly1305" {if $node->method=='2022-blake3-chacha20-poly1305'}selected{/if}>
                                            2022-blake3-chacha20-poly1305
                                        </option>
                                        <option value="2022-blake3-aes-128-gcm" {if $node->method=='2022-blake3-aes-128-gcm'}selected{/if}>
                                            2022-blake3-aes-128-gcm
                                        </option>
                                        <option value="2022-blake3-aes-256-gcm" {if $node->method=='2022-blake3-aes-256-gcm'}selected{/if}>
                                            2022-blake3-aes-256-gcm
                                        </option>
                                    </select>
                                </div>
                                <div class="form-group form-group-label">
                                    <label class="floating-label" for="rate">流量比例</label>
                                    <input class="form-control maxwidth-edit" id="rate" name="rate" type="text" value="{$node->traffic_rate}">
                                </div>
                                <div class="form-group form-group-label" hidden="hidden">
                                    <div class="checkbox switch">
                                        <label for="custom_method">
                                            <input {if $node->custom_method==1}checked{/if} class="access-hide" id="custom_method" name="custom_method" type="checkbox">
                                            <span class="switch-toggle"></span>
                                            自定义加密
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group form-group-label" hidden="hidden">
                                    <div class="checkbox switch">
                                        <label for="custom_rss">
                                            <input {if $node->custom_rss==1}checked{/if} class="access-hide"
                                                   id="custom_rss" type="checkbox" name="custom_rss"><span
                                                    class="switch-toggle"></span>自定义协议&混淆
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-main">
                            <div class="card-inner">
                                <div class="form-group form-group-label">
                                    <div class="checkbox switch">
                                        <label for="type">
                                            <input {if $node->type==1}checked{/if} class="access-hide" id="type"
                                                   name="type" type="checkbox"><span class="switch-toggle"></span>是否显示
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group form-group-label">
                                    <label class="floating-label" for="status">节点状态</label>
                                    <input class="form-control maxwidth-edit" id="status" name="status" type="text" value="{$node->status}">
                                </div>
                                <div class="form-group form-group-label">
                                    <label class="floating-label" for="info">节点描述</label>
                                    <input class="form-control maxwidth-edit" id="info" name="info" type="text" value="{$node->info}">
                                </div>
                                <div class="form-group form-group-label">
                                    <label class="floating-label" for="class">节点等级</label>
                                    <input class="form-control maxwidth-edit" id="class" name="class" type="text" value="{$node->node_class}">
                                    <p class="form-control-guide"><i class="material-icons">info</i>不分级请填0，分级填写相应数字
                                    </p>
                                </div>
                                <div class="form-group form-group-label">
                                    <label class="floating-label" for="group">节点群组</label>
                                    <input class="form-control maxwidth-edit" id="group" name="group" type="text" value="{$node->node_group}">
                                    <p class="form-control-guide"><i class="material-icons">info</i>分组为数字，不分组请填0
                                    </p>
                                </div>
                                <div class="form-group form-group-label">
                                    <label class="floating-label" for="node_bandwidth_limit">节点流量上限（GB）</label>
                                    <input class="form-control maxwidth-edit" id="node_bandwidth_limit"
                                           name="node_bandwidth_limit" type="text"
                                           value="{$node->node_bandwidth_limit/1024/1024/1024}">
                                    <p class="form-control-guide"><i class="material-icons">info</i>不设上限请填0</p>
                                </div>
                                <div class="form-group form-group-label">
                                    <label class="floating-label" for="bandwidthlimit_resetday">节点流量上限清空日</label>
                                    <input class="form-control maxwidth-edit" id="bandwidthlimit_resetday"
                                           name="bandwidthlimit_resetday" type="text"
                                           value="{$node->bandwidthlimit_resetday}">
                                </div>
                                <div class="form-group form-group-label">
                                    <label class="floating-label" for="node_speedlimit">节点限速（Mbps）</label>
                                    <input class="form-control maxwidth-edit" id="node_speedlimit"
                                           name="node_speedlimit" type="text" value="{$node->node_speedlimit}">
                                    <p class="form-control-guide"><i class="material-icons">info</i>不限速填0，对于每个用户端口生效
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-main">
                            <div class="card-inner">
                                <div class="form-group">
                                    <div class="row">
                                        <div class="col-md-10 col-md-push-1">
                                            <button id="submit" type="submit" class="btn btn-block btn-brand waves-attach waves-light">修改
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
                {include file='dialog.tpl'}
        </div>
    </div>
</main>

{include file='admin/footer.tpl'}

{literal}
<script>
    const sortSelect = document.getElementById("sort");
    const serverInput = document.getElementById("server");
    const vlessSwitchGroup = document.getElementById("vless_switch_group");
    const vlessSwitch = document.getElementById("enable_vless");
    const methodGroup = document.getElementById("method_group");
    const vlessGroup = document.getElementById("vless_group");
    const flowSelect = document.getElementById("flow_select");
    const securitySelect = document.getElementById("security_select");
    const securityOptionGroup = document.getElementById("reality_option_group");
    document.addEventListener("DOMContentLoaded", function () {
        function toggleVlessSwitch() {
            if (sortSelect.value === "11") {
                vlessSwitchGroup.style.display = "block";
                checkServerVless();
            } else {
                vlessSwitchGroup.style.display = "none";
            }
        }

        function checkServerVless() {
            const server = serverInput.value;
            if (server.includes("enable_vless=true")) {
                vlessSwitch.checked = true;
            } else {
                vlessSwitch.checked = false;
            }
        }

        function toggleMethod() {
            if (sortSelect.value === "0") {
                methodGroup.style.display = "block";  // 显示
            } else {
                methodGroup.style.display = "none";   // 隐藏
            }
        }

        function toggleFlow() {
            if (vlessSwitch.checked) {
                vlessGroup.style.display = "block";
                readFlowFromServer();
                readSecurityFromServer();
            } else {
                vlessGroup.style.display = "none";
            }
        }

        function toggleRealityOption() {
            if (securitySelect.value === "Reality") {
                securityOptionGroup.style.display = "block";
            } else {
                securityOptionGroup.style.display = "none";
            }
        }

        function readFlowFromServer() {
            const server = serverInput.value;
            const match = server.match(/flow=([^|]+)/);
            if (match) {
                flowSelect.value = match[1];
            } else {
                flowSelect.value = "";
            }
        }

        function readSecurityFromServer() {
            const server = serverInput.value;
            if (server.includes(";tls;")) {
                securitySelect.value = "TLS";
            } else if (server.includes(";reality;")) {
                securitySelect.value = "Reality";
            } else {
                securitySelect.value = "无";
            }
        }

        // 页面加载时执行一次
        toggleVlessSwitch();
        toggleMethod();
        toggleFlow();
        toggleRealityOption()
        readFlowFromServer();
        readSecurityFromServer();
        // 监听变化
        sortSelect.addEventListener("change", toggleMethod);
        sortSelect.addEventListener("change", toggleVlessSwitch);
        securitySelect.addEventListener("change", toggleRealityOption);
        serverInput.addEventListener("input", checkServerVless);
        vlessSwitch.addEventListener("change", toggleFlow);
        vlessSwitch.addEventListener("change", function () {
            let server = serverInput.value;
            if (vlessSwitch.checked) {
                // 如果存在 enable_vless=false → 改成 true
                if (server.includes("enable_vless=false")) {
                    server = server.replace("enable_vless=false", "enable_vless=true");
                }
                // 如果没有 enable_vless 参数 → 追加
                else if (!server.includes("enable_vless=true")) {
                    server += "|enable_vless=true";
                }
            } else {
                server = server.replace("|enable_vless=true", "");
                server = server.replace(/\|?flow=[^|]+/g, "");
            }
            serverInput.value = server;
        });
        flowSelect.addEventListener("change", function () {
            let server = serverInput.value;
            const flow = flowSelect.value;
            // 删除已有 flow
            server = server.replace(/\|?flow=[^|]+/g, "");
            if (flow !== "") {
                server += "|flow=" + flow;
            }
            serverInput.value = server;
        });
        securitySelect.addEventListener("change", function () {
            let server = serverInput.value;
            const security = securitySelect.value;
            serverInput.value = server.replace(/^((?:[^;]*;){4})[^;]*/, "$1" + security.toLowerCase());
        });
        $("#generate_key").click(function (e) {
            e.preventDefault();
            $.ajax({
                type: "GET",
                url: "/admin/generate_reality_key",
                dataType: "json",
                success: function (data) {
                    $("#private_key").val(data.privateKey).trigger("change");
                    $("#public_key").val(data.publicKey).trigger("change");
                },
                error: function () {
                    alert("生成密钥失败");
                }
            });
        });
    });

    function buildJson() {
        const data = {
            dest: document.getElementById("dest").value,
            private_key: document.getElementById("private_key").value,
            public_key: document.getElementById("public_key").value,
            short_id: null
        };
        const json = JSON.stringify(data);
        console.log(json);
        return json;
    }

    $('#main_form').validate({
        rules: {
            name: {required: true},
            server: {required: true},
            method: {required: true},
            rate: {required: true},
            info: {required: true},
            group: {required: true},
            status: {required: true},
            node_speedlimit: {required: true},
            sort: {required: true},
            node_bandwidth_limit: {required: true},
            bandwidthlimit_resetday: {required: true}
        },
        submitHandler: () => {
            let method;
            let custom_method;
            if (sortSelect.value === "0") {
                custom_method = 0;
                method = $$getValue('method');
            } else {
                custom_method = 1;
                method = buildJson();
            }
            let sort;
            if ($$.getElementById('enable_vless').checked) {
                sort = 12;
            } else {
                sort = $$getValue('sort');
            }
            let type;
            if ($$.getElementById('type').checked) {
                type = 1;
            } else {
                type = 0;
            }
            {/literal}
            let custom_rss;
            if ($$.getElementById('custom_rss').checked) {
                custom_rss = 1;
            } else {
                custom_rss = 0;
            }
            $.ajax({
                type: "PUT",
                url: "/admin/node/{$node->id}",
                dataType: "json",
                {literal}
                data: {
                    name: $$getValue('name'),
                    server: $$getValue('server'),
                    node_ip: $$getValue('node_ip'),
                    method,
                    custom_method,
                    rate: $$getValue('rate'),
                    info: $$getValue('info'),
                    type,
                    group: $$getValue('group'),
                    status: $$getValue('status'),
                    sort,
                    node_speedlimit: $$getValue('node_speedlimit'),
                    class: $$getValue('class'),
                    node_bandwidth_limit: $$getValue('node_bandwidth_limit'),
                    bandwidthlimit_resetday: $$getValue('bandwidthlimit_resetday')
                    {/literal},
                    custom_rss
                },
                success: (data) => {
                    if (data.ret) {
                        $("#result").modal();
                        $$.getElementById('msg').innerHTML = data.msg;
                        window.setTimeout("location.href=top.document.referrer", {$config['jump_delay']});

                    } else {
                        $("#result").modal();
                        $$.getElementById('msg').innerHTML = data.msg;
                    }
                },
                {literal}
                error: (jqXHR) => {
                    $("#result").modal();
                    $$.getElementById('msg').innerHTML = `发生错误：${jqXHR.status}`;
                }
            });
        }
    });
    {/literal}
</script>
