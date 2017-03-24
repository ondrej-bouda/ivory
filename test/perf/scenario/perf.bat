@echo php bench.php --recreate --rounds 100 --warmup 5 --busyloop 100000 IMPL > perf.txt
@echo measured on ThinkPad T540p >> perf.txt

@echo( >> perf.txt
@echo pgsql: >> perf.txt
php bench.php --recreate --rounds 100 --warmup 5 --busyloop 100000 pgsql >> perf.txt

@echo( >> perf.txt
@echo ivory: >> perf.txt
php bench.php --recreate --rounds 100 --warmup 5 --busyloop 100000 ivory >> perf.txt

@echo( >> perf.txt
@echo ivory-sync: >> perf.txt
php bench.php --recreate --rounds 100 --warmup 5 --busyloop 100000 ivory-sync >> perf.txt

@echo( >> perf.txt
@echo dibi: >> perf.txt
php bench.php --recreate --rounds 100 --warmup 5 --busyloop 100000 dibi >> perf.txt

@echo( >> perf.txt
@echo dibi-lazy: >> perf.txt
php bench.php --recreate --rounds 100 --warmup 5 --busyloop 100000 dibi-lazy >> perf.txt
