/**
 * Copyright © Pathly. All rights reserved.
 * See LICENSE.txt for license details (OSL-3.0).
 */
define(['jquery'], function ($) {
    'use strict';

    var POLL_MS = 4000,
        MAX_POLLS = 75;

    return function (config, element) {
        var $root = $(element),
            $owner = $root.find('.pathly-lumi-owner'),
            $start = $root.find('.pathly-lumi-start'),
            $out = $root.find('.pathly-lumi-output'),
            text = config.text;

        /**
         * Post to an admin action, with the form key checked by Magento.
         *
         * @param {String} url
         * @param {Object} data
         * @return {jQuery.Deferred}
         */
        function post(url, data) {
            return $.ajax({
                url: url,
                type: 'POST',
                dataType: 'json',
                data: $.extend({'form_key': window.FORM_KEY, isAjax: true}, data)
            });
        }

        /**
         * Show a message.
         *
         * @param {String} message
         * @param {String} type
         */
        function say(message, type) {
            $out.empty().append($('<div></div>').addClass('message message-' + type).text(message));
        }

        /**
         * One proposal with its button.
         *
         * @param {String} trial
         * @param {Object} proposal
         * @return {jQuery}
         */
        function proposalRow(trial, proposal) {
            var label = text['kind_' + proposal.kind] || text.kind_custom,
                detail = text.steps.replace('%1', proposal.steps),
                $row = $('<div></div>').addClass('pathly-lumi-proposal'),
                $button = $('<button type="button"></button>').addClass('action-primary');

            if (proposal.stoppedBefore) {
                detail += ', ' + text.stoppedBefore.replace('%1', proposal.stoppedBefore);
            }
            $row.append($('<strong></strong>').text(label), ' (' + detail + ') ');

            if (proposal.saved) {
                return $row.append($('<em></em>').text(text.already));
            }
            $button.text(text.validate).on('click', function () {
                $button.prop('disabled', true);
                post(config.saveUrl, {trial: trial, index: proposal.index}).done(function (res) {
                    if (res.ok) {
                        $row.empty().append($('<div></div>').addClass('message message-success').text(text.saved));
                    } else {
                        $button.prop('disabled', false);
                        $row.append($('<div></div>').addClass('message message-error').text(res.message));
                    }
                }).fail(function () {
                    $button.prop('disabled', false);
                    $row.append($('<div></div>').addClass('message message-error').text(text.error));
                });
            });

            return $row.append($button);
        }

        /**
         * Poll the trial until Lumi is done.
         *
         * @param {String} trial
         * @param {Number} count
         */
        function poll(trial, count) {
            post(config.statusUrl, {trial: trial}).done(function (res) {
                if (!res.ok) {
                    $start.prop('disabled', false);
                    return say(res.message, 'error');
                }
                if (!res.done) {
                    if (count >= MAX_POLLS) {
                        $start.prop('disabled', false);
                        return say(text.failed, 'error');
                    }
                    return setTimeout(function () {
                        poll(trial, count + 1);
                    }, POLL_MS);
                }
                $start.prop('disabled', false);
                if (!res.proposals.length) {
                    return say(res.status === 'failed' ? text.failed : text.none, 'warning');
                }
                $out.empty();
                $.each(res.proposals, function (i, proposal) {
                    $out.append(proposalRow(trial, proposal));
                });
            }).fail(function () {
                $start.prop('disabled', false);
                say(text.error, 'error');
            });
        }

        $owner.on('change', function () {
            $start.prop('disabled', !$owner.is(':checked'));
        });

        $start.on('click', function () {
            $start.prop('disabled', true);
            say(text.working, 'notice');
            post(config.startUrl, {'confirm_owner': $owner.is(':checked') ? 1 : 0}).done(function (res) {
                if (!res.ok) {
                    $start.prop('disabled', false);
                    return say(res.message, 'error');
                }
                poll(res.trialId, 0);
            }).fail(function () {
                $start.prop('disabled', false);
                say(text.error, 'error');
            });
        });
    };
});
