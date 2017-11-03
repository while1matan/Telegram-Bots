# Telegram-Bots
PHP class to easily create bots for telegram.

## Changelog
### v2 - 2017-11-03
- [_Added_] PHP errors handling: [*Runtime Errors*](http://www.php.net/set_error_handler) will be logged in the bot's log file.
- [_Added_] `secret` GET-param check, use `$bot->restrictAccess(WEBHOOK_SECRET);` to set an access code.
- [_Added_] Webhook set/unset check, use `$bot->handleWebhook(WEBHOOK_URL);` to set a webhook url.
- [_Fixed_] update-id bug. The [update_id sequence](https://core.telegram.org/bots/api#update) is considered old after 24 hours.
- [_Changed_] Bot's username is now required and being stored on class creation.
- [_Changed_] `findCommand()` doesn't get the `$botUserName` parameter anymore.
- [_Changed_] `InlineKeyboard` converted to a new object.

## Bots using this class
### My Bots:
- [textFormat](https://telegram.me/w1_format_bot) ([Source](format_bot))
- [2Gif](https://telegram.me/w1_gif_bot)
- [MathFormula](https://telegram.me/w1_math_bot)
- [FuelPricesIL](https://telegram.me/w1_fuel_bot)
- [EvalBot](https://telegram.me/w1_eval_bot)

### Others Bots:
- [Random Things Picker](https://telegram.me/w1_pick_bot) (by [Asaf](https://github.com/amnonya))
- [Yes No](https://telegram.me/w1_yes_no_bot) (by [Asaf](https://github.com/amnonya) | [Source](https://github.com/amnonya/Yes-No-Telegram-Bot/))
