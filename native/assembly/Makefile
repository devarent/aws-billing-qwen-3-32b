# Makefile untuk contoh assembly (GAS / MinGW)
CC ?= gcc
CFLAGS ?= -O2 -Wall

.PHONY: all clean run

all: hello.exe arith.exe

hello.exe: hello.s
	$(CC) $(CFLAGS) $< -o $@

arith.exe: arith.s
	$(CC) $(CFLAGS) $< -o $@

run: all
	./hello.exe
	./arith.exe

clean:
	rm -f hello.exe arith.exe
