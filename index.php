<?php
/******************************************************************************
 *
 * Subrion - open source content management system
 * Copyright (C) 2017 Intelliants, LLC <https://intelliants.com>
 *
 * This file is part of Subrion.
 *
 * Subrion is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Subrion is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Subrion. If not, see <http://www.gnu.org/licenses/>.
 *
 *
 * @version 1.1
 * @link https://subrion.org/
 *
 ******************************************************************************/

function json_response($data = null, $status = 200)
{
    http_response_code($status);
    header("Content-type: application/json");
    die(json_encode($data));
}

function download($f, $to)
{
    $error = false;
    if (in_array('curl', get_loaded_extensions())) {
        $cp = curl_init($f);
        $fp = fopen($to, "w+");
        if (!$fp) {
            curl_close($cp);
            $error = 'perms';
        } else {
            curl_setopt($cp, CURLOPT_FILE, $fp);
            curl_setopt($cp, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($cp, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($cp, CURLOPT_SSL_VERIFYPEER, false);
            if (curl_exec($cp) === false) {
                fclose($fp);
                @unlink($to);
                $error = curl_error($cp);
            } else {
                fclose($fp);
            }
            curl_close($cp);
        }
    } else {
        $error = 'extensions';
    }

    return [file_exists($to) && filesize($to) > 0, $error];
}

function errors()
{
    $data = [];
    if (!version_compare('5.5', PHP_VERSION, '<')) {
        $error = 'PHP version is not compatible. PHP 5.5+ needed. (Current version ' . PHP_VERSION . ')';
        array_push($data, ['type' => 'danger', 'text' => $error]);
    }
    if (!function_exists('mysqli_connect')) {
        $error = 'MySQLi support unavailable (required)';
        array_push($data, ['type' => 'danger', 'text' => $error]);
    }
    if (!extension_loaded('curl')) {
        $error = 'CURL extension is not present. Please consider installing it';
        array_push($data, ['type' => 'danger', 'text' => $error]);
    }

    $recommendedExtensions = [
        ['xml', 'XML support is not available (recommended)'],
        ['gd', 'GD extension is not available (highly recommended)'],
        ['mbstring', 'Mbstring extension is not available (not required)']
    ];
    foreach ($recommendedExtensions as $extension) {
        if (!extension_loaded($extension[0])) {
            array_push($data, ['type' => 'warning', 'text' => $extension[1]]);
        }
    }

    $recommendedSettings = [
        ['file_uploads', true, 'File upload is disabled (not recommended)'],
        ['magic_quotes_gpc', false, 'Magic Quotes GPC is enabled (not recommended)'],
        ['register_globals', false, 'Register Globals is enabled (not recommended)']
    ];
    foreach ($recommendedSettings as $setting) {
        if ((ini_get($setting[0]) == '1') != $setting[1]) {
            array_push($data, ['type' => 'warning', 'text' => $setting[2]]);
        }
    }

    return json_encode($data);
}

function install()
{
    list($success, $error) = download('https://raw.githubusercontent.com/intelliants/subrion/master/includes/utils/pclzip.lib.php', 'pclzip.lib.php');
    if (!$success) {
        json_response(["error" => $error], 400);
    }

    list($success, $error) = download('https://tools.subrion.org/get/latest.zip', 'subrion.zip');
    if (!$success) {
        json_response(["error" => $error], 400);
    }

    require 'pclzip.lib.php';

    unlink('index.php');

    $archive = new PclZip('subrion.zip');
    $archive->extract();

    unlink('pclzip.lib.php');
}

function isAjaxRequest()
{
    return (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest');
}

if (isAjaxRequest()) {
    install();
    json_response();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Subrion auto-installer</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.1.1/jquery.min.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/3.3.7/css/bootstrap.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Roboto:400,400i,700" rel="stylesheet">
    <script>
        $(function () {
            var errors = <?= errors() ?>;
            var $button = $('#install');
            var $errors = $('#errors');
            var $elErrors = $('#error-block');
            var $elAction = $('#action-block');
            var $elSuccess = $('#success');
            var $elProgress = $('#progress-block');

            $.each(errors, function (i, err) {
                $errors.append('<div class="alert alert-' + err.type + '">' + err.text + '</div>');
            });

            var num_errors = $.grep(errors, function (err) {
                return err.type == 'danger'
            }).length;
            if (!num_errors) {
                $elAction.show();

                $button.on('click', function () {
                    $elAction.hide();
                    $elProgress.show();

                    $.post('', {})
                        .done(function () {
                            $elSuccess.show();
                            setTimeout(location.reload.bind(location), 2000);
                        })
                        .always(function () {
                            $button.prop('disabled', false);
                        });
                });
            } else {
                $elErrors.show();
            }
        });
    </script>

    <style>
        html,
        body {
            height: 100%;
            margin: 0;
            padding: 0;
            font-family: 'Roboto', sans-serif;
        }

        .lt {
            display: table;
            width: 100%;
            height: 100%;
            margin: 0;
        }

        .lt__row {
            display: table-row;
        }

        .lt__content,
        .lt__footer {
            display: table-cell;
            vertical-align: middle;
            text-align: center;
            height: 100%;
            background: #f2f2f2;
        }

        .lt__footer {
            height: 80px;
            font-size: 12px;
            color: #999;
            position: relative;
            border-top: 1px solid #ddd;
            padding-left: 30px;
            padding-right: 30px;
        }

        .lt__footer h3 {
            margin: 0;
            font-size: 20px;
            line-height: 30px;
            float: left;
        }

        .lt__footer p {
            margin: 0;
            float: right;
            line-height: 30px;
        }

        .lt__footer a,
        .lt__footer a:focus {
            color: #777;
            text-decoration: underline;
        }

        .lt__footer a:hover {
            color: #337ab7;
            text-decoration: none;
        }

        .logo {
            width: 30px;
            height: 30px;
            background: url('data:image/png;base64,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') 0 0 no-repeat;
            -webkit-background-size: 30px 30px;
            background-size: 30px 30px;
            display: inline-block;
            margin-right: 10px;
            margin-top: -7px;
            vertical-align: middle;
        }

        .card {
            width: 40%;
            margin: 0 auto;
            background: #fff;
            box-shadow: 0 15px 50px rgba(0, 0, 0, .1);
            padding: 50px;
            border-radius: 6px;
        }

        .card .text-success,
        .card .text-danger,
        .card .text-info {
            font-size: 18px;
            margin: 20px 0 40px;
        }

        .card p:last-child {
            margin: 0;
        }

        .card .btn {
            padding-left: 50px;
            padding-right: 50px;
            border-radius: 30px;
        }

        .card .help-block {
            margin-bottom: 0;
        }

        .errors {
            margin-bottom: 20px;
        }

        .errors .alert {
            margin: -1px 0 0;
            padding: 10px;
            border-radius: 0;
            text-align: left;
            font-size: 13px;
        }

        .sk-folding-cube {
            margin: 20px auto;
            width: 50px;
            height: 50px;
            position: relative;
            -webkit-transform: rotateZ(45deg);
            transform: rotateZ(45deg);
        }

        .sk-folding-cube .sk-cube {
            float: left;
            width: 50%;
            height: 50%;
            position: relative;
            -webkit-transform: scale(1.1);
            -ms-transform: scale(1.1);
            transform: scale(1.1);
        }

        .sk-folding-cube .sk-cube:before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: #337ab7;
            -webkit-animation: sk-foldCubeAngle 2.4s infinite linear both;
            animation: sk-foldCubeAngle 2.4s infinite linear both;
            -webkit-transform-origin: 100% 100%;
            -ms-transform-origin: 100% 100%;
            transform-origin: 100% 100%;
        }

        .sk-folding-cube .sk-cube2 {
            -webkit-transform: scale(1.1) rotateZ(90deg);
            transform: scale(1.1) rotateZ(90deg);
        }

        .sk-folding-cube .sk-cube3 {
            -webkit-transform: scale(1.1) rotateZ(180deg);
            transform: scale(1.1) rotateZ(180deg);
        }

        .sk-folding-cube .sk-cube4 {
            -webkit-transform: scale(1.1) rotateZ(270deg);
            transform: scale(1.1) rotateZ(270deg);
        }

        .sk-folding-cube .sk-cube2:before {
            -webkit-animation-delay: 0.3s;
            animation-delay: 0.3s;
        }

        .sk-folding-cube .sk-cube3:before {
            -webkit-animation-delay: 0.6s;
            animation-delay: 0.6s;
        }

        .sk-folding-cube .sk-cube4:before {
            -webkit-animation-delay: 0.9s;
            animation-delay: 0.9s;
        }

        @-webkit-keyframes sk-foldCubeAngle {
            0%, 10% {
                -webkit-transform: perspective(140px) rotateX(-180deg);
                transform: perspective(140px) rotateX(-180deg);
                opacity: 0;
            }
            25%, 75% {
                -webkit-transform: perspective(140px) rotateX(0deg);
                transform: perspective(140px) rotateX(0deg);
                opacity: 1;
            }
            90%, 100% {
                -webkit-transform: perspective(140px) rotateY(180deg);
                transform: perspective(140px) rotateY(180deg);
                opacity: 0;
            }
        }

        @keyframes sk-foldCubeAngle {
            0%, 10% {
                -webkit-transform: perspective(140px) rotateX(-180deg);
                transform: perspective(140px) rotateX(-180deg);
                opacity: 0;
            }
            25%, 75% {
                -webkit-transform: perspective(140px) rotateX(0deg);
                transform: perspective(140px) rotateX(0deg);
                opacity: 1;
            }
            90%, 100% {
                -webkit-transform: perspective(140px) rotateY(180deg);
                transform: perspective(140px) rotateY(180deg);
                opacity: 0;
            }
        }
    </style>
</head>
<body>
<div class="lt">
    <div class="lt__row">
        <div class="lt__content">
            <div class="card -error" id="error-block" style="display: none;">
                <svg width="50" height="50" version="1.1" id="Capa_2" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 50 50" style="enable-background:new 0 0 50 50;" xml:space="preserve">
                    <circle style="fill:#D75A4A;" cx="25" cy="25" r="25"/>
                    <polyline style="fill:none;stroke:#FFFFFF;stroke-width:2;stroke-linecap:round;stroke-miterlimit:10;" points="16,34 25,25 34,16"/>
                    <polyline style="fill:none;stroke:#FFFFFF;stroke-width:2;stroke-linecap:round;stroke-miterlimit:10;" points="16,16 25,25 34,34"/>
                </svg>

                <p class="text-danger">Your server is not ready for installation.<br>Please review the errors below</p>
                <div class="errors" id="errors"></div>
                <p class="help-block">When you are ready, hit <kbd>F5</kbd> to recheck configuration.</p>
            </div>
            <div class="card -success" id="action-block" style="display: none;">
                <svg width="50" height="50" version="1.1" id="Capa_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 50 50" style="enable-background:new 0 0 50 50;" xml:space="preserve">
                    <circle style="fill:#25AE88;" cx="25" cy="25" r="25"/>
                    <polyline style="fill:none;stroke:#FFFFFF;stroke-width:2;stroke-linecap:round;stroke-linejoin:round;stroke-miterlimit:10;" points="38,15 22,33 12,25 "/>
                </svg>

                <p class="text-success">System checks performed.<br>You are good to go!</p>
                <p>
                    <button type="submit" class="btn btn-default" id="install">Install Subrion CMS</button>
                </p>
            </div>
            <div class="card -progress" id="progress-block" style="display: none;">
                <div class="sk-folding-cube">
                    <div class="sk-cube1 sk-cube"></div>
                    <div class="sk-cube2 sk-cube"></div>
                    <div class="sk-cube4 sk-cube"></div>
                    <div class="sk-cube3 sk-cube"></div>
                </div>
                <p class="text-info">Installing Subrion CMS...<br>Please wait a bit.</p>
            </div>
            <div class="card -ready" id="success-block" style="display: none;">
                <svg width="50" height="50" version="1.1" id="Capa_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px" viewBox="0 0 56 56" style="enable-background:new 0 0 56 56;" xml:space="preserve">
                    <rect x="1.5" y="20" style="fill:#4B6DAA;" width="14" height="36"/>
                    <circle style="fill:#D8A852;" cx="8.5" cy="47" r="4"/>
                    <path style="fill:#FBCE9D;" d="M53.5,26c0-2.209-1.791-4-4-4h-9h-3h-3.602l0.988-4.619c0.754-3.524,0.552-7.819,0.104-10.836C34.542,3.528,31.84,0,29.013,0h-0.239C26.364,0,25.5,2.659,25.5,6c0,16.25-8,16-8,16h-2v32h15h10h4c2.209,0,4-1.791,4-4c0-2.209-1.791-4-4-4h3c2.209,0,4-1.791,4-4c0-2.209-1.791-4-4-4h3c2.209,0,4-1.791,4-4c0-2.493-1.613-3.442-4-3.796C49.337,30.031,47.224,30,46.5,30h3C51.709,30,53.5,28.209,53.5,26z"/>
                    <path style="fill:#F7B563;" d="M52.12,29H39.5c-0.552,0-1,0.447-1,1s0.448,1,1,1h13.456c-0.657-0.403-1.488-0.653-2.456-0.796C49.337,30.031,47.224,30,46.5,30h3C50.508,30,51.417,29.615,52.12,29z"/>
                    <path style="fill:#F7B563;" d="M53.12,37H39.5c-0.552,0-1,0.447-1,1s0.448,1,1,1h10.621c-0.703-0.615-1.613-1-2.621-1h3C51.508,38,52.417,37.615,53.12,37z"/>
                    <path style="fill:#F7B563;" d="M50.12,45H37.5c-0.552,0-1,0.447-1,1s0.448,1,1,1h9.621c-0.703-0.615-1.613-1-2.621-1h3C48.508,46,49.417,45.615,50.12,45z"/>
                </svg>

                <p class="text-info">Everything is done!<br>Redirecting in a moment...</p>
            </div>
        </div>
    </div>
    <div class="lt__row">
        <div class="lt__footer">
            <h3><span class="logo"></span> Subrion auto-installer</h3>
            <p>Copyright © 2008-<?= date('Y'); ?> <a target="_blank" href="https://intelliants.com/">Intelliants LLC</a>
            </p>
        </div>
    </div>
</div>
</body>
</html>