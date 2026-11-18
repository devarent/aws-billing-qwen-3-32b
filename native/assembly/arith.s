# arith.s — menjumlahkan 1..10 lalu mencetak hasilnya
# Build: gcc -O2 -Wall arith.s -o arith.exe

    .text
    .globl main

main:
    subq    $40, %rsp           # shadow space + alignment

    movl    $1, %eax            # i = 1
    xorl    %edx, %edx          # sum = 0

.Lloop:
    addl    %eax, %edx          # sum += i
    incl    %eax                # i++
    cmpl    $10, %eax
    jle     .Lloop              # ulangi selama i <= 10

    leaq    fmt(%rip), %rcx     # argumen 1: format string
    movl    %edx, %edx          # zero-extend sum ke RDX (argumen 2)
    call    printf

    xorl    %eax, %eax          # return 0
    addq    $40, %rsp
    ret

    .section .rdata, "dr"
fmt:
    .asciz  "Sum 1..10 = %d\n"
