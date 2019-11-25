#!/bin/bash

# https://blog.christophersmart.com/2016/05/11/running-scripts-before-and-after-suspend-with-systemd/comment-page-1/
# Simply put an executable script of any name under /usr/lib/systemd/system-sleep/ that 
# checks whether the first argument is pre (for before the system suspends) or post (after 
# the system wakes from suspend).

if [ "${1}" == "post" ]; then
    echo "$(date)" > /root/suspend-server/var/last-wakeup
    /root/suspend-server/bin/console bigpapa:stats
fi
