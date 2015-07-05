# GlotPress Import Export

This was developed for LimeSurvey in May 2012. It runs as a cron, automatically [adds](https://github.com/LimeSurvey/LimeSurvey/commit/32e9401a103162b406e20d0abf37597622a15d38) new strings pushed into project code to GlotPress and [commits](https://github.com/LimeSurvey/LimeSurvey/commit/d0581daf154110d2d166426aae39cfc49296abda) new translations back into the project. Check [this post](http://gaut.am/?p=1820) for an explanation.

The two files you would want to be concerned with are:
 * cron-gp-export.php
 * cron-gp-import.php
