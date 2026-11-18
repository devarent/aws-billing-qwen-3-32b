# hello.s — contoh Assembly x86-64 (GAS, Windows/MinGW)
# Build: gcc -O2 -Wall hello.s -o hello.exe

    .text
    .globl main

main:
    subq    $40, %rsp           # shadow space (32) + alignment (8)
    leaq    msg(%rip), %rcx     # argumen 1: format string
    call    printf
    xorl    %eax, %eax          # return 0
    addq    $40, %rsp
    ret

    .section .rdata, "dr"
msg:
    .asciz  "Hello from x86-64 Assembly (GAS)!\n"
