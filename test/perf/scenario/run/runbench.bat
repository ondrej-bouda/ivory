@echo php bench.php --recreate --rounds 100 --warmup 5 --busyloop 100000 IMPL > ../out/runbench.out
@echo measured on ThinkPad T540p >> ../out/runbench.out

runbench_pgsql.cmd
runbench_ivory.cmd
runbench_ivory-sync.cmd
runbench_dibi.cmd
runbench_dibi-lazy.cmd
runbench_doctrine.cmd
