Net_Notifier
============

Net_Notifier is a system for sending and receiving messages from PHP programs using WebSockets.

Net_Notifier provides a server for listeing and relaying notifications, a sender, which can send notifications to a server and a listener which can receive relayed notifications from a server.

An example use-case is sending order notifications when an order is placed on an e-commerce site.

Server
------

Net_Notifier comes with a CLI server for receiving and relaying notifications. It has the following interface:

<pre>
Service that receives and relays notifications to listening clients.

Usage:
  net-notifier-server [options]

Options:
  -p port, --port=port  Optional port on which to listen for notifications.
                        If not specified, port 3000 is used.
  -v, --verbose         Sets verbosity level. Use multiples for more detail
                        (e.g. "-vv").
  -h, --help            show this help message and exit
  --version             show the program version and exit
</pre>

This is a super cool test.
