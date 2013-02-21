/**
 *  Hub Client block
 *  
 *  @author  VERSION2, Inc.
 *  @version $Id: module.js 218 2013-02-21 13:11:22Z malu $
 */
YUI.add('block_hub_client', function (Y)
{
    M.block_hub_client = new function ()
    {
        /** @var {Node}  The MAJ Hub Client block container node */
        var $block = Y.Node.one('.block_hub_client');

        /** @var {Object}  The current course */
        var course = {
            id: $block.one('a.editing_edit').get('href').match(/\/course\/view\.php\?id=(\d+)/)[1]
        };

        /** @var {String[]}  The uploading server IDs */
        var uploading_servers = [];

        /**
         *  Show an error message with given Ajax error
         *  
         *  @param {Object} response  The Ajax response
         */
        function error(response)
        {
            try {
                var ex = Y.JSON.parse(response.responseText);
                new M.core.exception({
                    name: M.str.block_hub_client['pluginname'],
                    message: ex.message,
                    fileName: ex.file,
                    lineNumber: ex.line,
                    stack: ex.trace.replace(/\n/, '<br />')
                });
            } catch (e) {
                new M.core.exception({
                    name: M.str.block_hub_client['pluginname'],
                    message: response.responseText
                });
            }
        }

        /**
         *  Post an Ajax request
         *  
         *  @param {String} action
         *  @param {Object} params
         *  @param {Function} callback({Object} response)
         */
        function post(action, params, callback)
        {
            var $spinner = M.util.add_spinner(Y, $block.one('.commands'));
            Y.io(M.cfg.wwwroot + '/blocks/hub_client/' + action + '.php', {
                method: 'POST',
                data: Y.merge(params, { 'sesskey': M.cfg.sesskey }),
                on: {
                    start: function (tid) { $spinner.show(); },
                    end: function (tid) { $spinner.remove(); },
                    success: function (tid, response) { callback(response); },
                    failure: function (tid, response) { error(response); }
                }
            });
        }

        /**
         *  Refresh the block content
         */
        function refresh()
        {
            post('list', { 'course': course.id }, function (response)
            {
                $block.one('.servers').replace(response.responseText);
                if ($block.one('.progressbar'))
                    setTimeout(refresh, 5000);

                uploading_servers = uploading_servers.filter(function (serverid)
                {
                    var $legend = $block.one('legend.block_hub_client-server-' + serverid);
                    if (!$legend)
                        return false; // server has been deleted while uploading??
                    var $editlink = $legend.ancestor().one('a[href*="edit.php"]');
                    if ($editlink) {
                        new M.core.confirm({
                            title: M.str.block_hub_client['uploadcompleted'],
                            question: M.str.block_hub_client['confirm:editmetadata']
                        }).on('complete-yes', function ()
                        {
                            location.href = $editlink.get('href');
                        });
                        return false;
                    }
                    return true;
                });
            });
        }

        /**
         *  Authenticate a Hub account
         *  
         *  @param {String} serverid
         *  @param {String} username
         *  @param {String} password
         *  @param {Function} callback
         */
        function auth(serverid, username, password, callback)
        {
            var params = { 'server': serverid };
            if (username && password) {
                params['username'] = username;
                params['password'] = password;
            }
            post('auth', params, function (response)
            {
                try {
                    var r = Y.JSON.parse(response.responseText);
                    if (r.succeeded) {
                        callback();
                        return;
                    }
                    // login account registration/update dialogue shows if authentication failed
                    var title = $block.one('.block_hub_client-server-' + serverid).get('text');
                    var $logincancel = new M.block_hub_client.logincancel({
                        title: title, loginLabel: M.str.moodle['login'], cancelLabel: M.str.moodle['cancel'],
                        usernameLabel: M.str.moodle['username'], passwordLabel: M.str.moodle['password']
                    });
                    $logincancel.on('complete-login', function (e, username, password)
                    {
                        // tries to authenticate with new account
                        auth(serverid, username, password, callback);
                    });
                } catch (e) {
                    error(response);
                }
            });
        }

        /**
         *  Initialize
         *  
         *  @param {YUI} Y
         */
        this.init = function (Y)
        {
            M.str.block_hub_client['pluginname'] = $block.one('h2').get('text');

            var m = /missingcourseware=(\d+)/.exec(location.hash);
            if (m && $block.one('a[href$="/local/majhub/edit.php?id=' + m[1] + '"]')) {
                var missingcoursewareid = m[1];
                new M.core.confirm({
                    title: M.str.block_hub_client['error:missingcourseware'],
                    question: M.str.block_hub_client['confirm:retryupload']
                }).on('complete-yes', function ()
                {
                    post('retry', { 'courseware': missingcoursewareid }, function (response)
                    {
                        var serverid = parseInt(response.responseText);
                        uploading_servers.push(serverid);
                        refresh();
                    });
                });
            }
            if ($block.one('.progressbar'))
                setTimeout(refresh, 5000);
        }

        /**
         *  Upload the current course to the MAJ Hub server
         *  
         *  @param {String} serverid
         */
        this.upload = function (serverid)
        {
            auth(serverid, null, null, function ()
            {
                // queue a course backup/upload task if authentication succeeded
                uploading_servers.push(serverid);
                post('queue', { 'server': serverid, 'course': course.id }, refresh);
            });
        }

        /**
         *  Cancel the queued upload task
         *  
         *  @param {String} serverid
         */
        this.cancel = function (serverid)
        {
            post('cancel', { 'server': serverid, 'course': course.id }, function ()
            {
                uploading_servers = uploading_servers.filter(function (id) { return id == serverid; });
                refresh();
            });
        }
    }

    /**
     *  Login/Cancel dialogue
     */
    var LOGINCANCEL = function (config)
    {
        LOGINCANCEL.superclass.constructor.apply(this, [config]);
    }
    Y.extend(LOGINCANCEL, M.core.confirm, {
        initializer: function (config)
        {
            var C = Y.Node.create;
            this.publish('complete');
            this.publish('complete-login');
            this.publish('complete-cancel');
            var _username = C('<label style="margin-right:4px"/>').append(this.get('usernameLabel'));
            var _password = C('<label style="margin-right:4px"/>').append(this.get('passwordLabel'));
            var $username = C('<input type="text" name="username" style="text-align:left"/>');
            var $password = C('<input type="password" name="password" style="text-align:left"/>');
            var $login = C('<input type="button"/>').set('value', this.get('loginLabel'));
            var $cancel = C('<input type="button"/>').set('value', this.get('cancelLabel'));
            var $content = C('<div class="confirmation-dialogue"/>')
                .append(C('<div class="confirmation-message"/>')
                    .append(C('<div style="width:22em"/>')
                        .append(C('<div style="text-align:right"/>').append(_username).append($username))
                        .append(C('<div style="text-align:right"/>').append(_password).append($password))
                        )
                    )
                .append(C('<div class="confirmation-buttons"/>').append($login).append($cancel));
            this.get('notificationBase').addClass('moodle-dialogue-confirm');
            this.setStdModContent(Y.WidgetStdMod.BODY, $content, Y.WidgetStdMod.REPLACE);
            this.setStdModContent(Y.WidgetStdMod.HEADER, this.get('title'), Y.WidgetStdMod.REPLACE);
            this.after('destroyedChange', function () { this.get('notificationBase').remove(); }, this);
            $login.on('click', this.submit, this, true);
            $cancel.on('click', this.submit, this, false);
            this.set('$username', $username);
            this.set('$password', $password);
            $username.focus();
        },
        submit: function (e, outcome)
        {
            this.fire('complete', outcome);
            if (outcome) {
                var username = this.get('$username').get('value');
                var password = this.get('$password').get('value');
                this.fire('complete-login', username, password);
            } else {
                this.fire('complete-cancel');
            }
            this.hide();
            this.destroy();
        }
    }, {
        NAME: "Moodle login dialogue",
        CSS_PREFIX: 'moodle-dialogue',
        ATTRS: {
            title        : { validator: Y.Lang.isString, value: "Login" },
            loginLabel   : { validator: Y.Lang.isString, value: "Login" },
            cancelLabel  : { validator: Y.Lang.isString, value: "Cancel" },
            usernameLabel: { validator: Y.Lang.isString, value: "Username" },
            passwordLabel: { validator: Y.Lang.isString, value: "Password" }
        }
    });
    Y.augment(LOGINCANCEL, Y.EventTarget);

    M.block_hub_client.logincancel = LOGINCANCEL;

}, '2.3, release candidate 1', { requires: [ 'base', 'node', 'io', 'dom', 'moodle-enrol-notification' ] });
