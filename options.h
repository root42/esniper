/*
 * Copyright (c) 2002, 2003, Scott Nicol <esniper@sourceforge.net>
 * All rights reserved
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * - Redistributions of source code must retain the above copyright notice,
 *   this list of conditions and the following disclaimer.
 * - Redistributions in binary form must reproduce the above copyright
 *   notice, this list of conditions and the following disclaimer in the
 *   documentation and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED
 * TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR
 * PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF
 * LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING
 * NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

#ifndef OPTIONS_H_INCLUDED
#define OPTIONS_H_INCLUDED

/* data types for option or config values */
#define OPTION_STRING   1
#define OPTION_INT      2
#define OPTION_BOOL     3
#define OPTION_BOOL_NEG 4
/* OPTION_SPECIAL does not specify data type, the checking function,
   which is mandatory here, must know what to do. The parsing function
   will provide the string value or NULL to the checking function */
#define OPTION_SPECIAL  5

/* table to describe all option or config values */
typedef struct optionTable optionTable_t;

struct optionTable {
   const char *configname; /* keyword in config files */
   const char *optionname; /* option without '-' */
   void *value;            /* variable to store value */
   int type;               /* data type of expected value or option argument */
   /* This function will be called to check and copy value if specified.
      It can get the value by other means than converting the string
      found in config file or on command line. */
   int (*checkfunc)(const void* valueptr, const optionTable_t* tableptr,
                    const char* filename, const char *line);
};

extern int readConfigFile(const char *filename, optionTable_t *table);
extern int parseGetoptValue(int option, const char *optval,
			    optionTable_t *table);

#endif /* OPTIONS_H_INCLUDED */
