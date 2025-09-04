<?php

namespace App\Enum;

use App\Enum\Trait\EnumUtilsTrait;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum Birthyear: string implements TranslatableInterface
{
    use EnumUtilsTrait;

    // Ordering
    // Condition::cases() returns an array of cases, in order of declaration.
    case Y1940 = '1940';
    case Y1941 = '1941';
    case Y1942 = '1942';
    case Y1943 = '1943';
    case Y1944 = '1944';
    case Y1945 = '1945';
    case Y1946 = '1946';
    case Y1947 = '1947';
    case Y1948 = '1948';
    case Y1949 = '1949';

    case Y1950 = '1950';
    case Y1951 = '1951';
    case Y1952 = '1952';
    case Y1953 = '1953';
    case Y1954 = '1954';
    case Y1955 = '1955';
    case Y1956 = '1956';
    case Y1957 = '1957';
    case Y1958 = '1958';
    case Y1959 = '1959';

    case Y1960 = '1960';
    case Y1961 = '1961';
    case Y1962 = '1962';
    case Y1963 = '1963';
    case Y1964 = '1964';
    case Y1965 = '1965';
    case Y1966 = '1966';
    case Y1967 = '1967';
    case Y1968 = '1968';
    case Y1969 = '1969';

    case Y1970 = '1970';
    case Y1971 = '1971';
    case Y1972 = '1972';
    case Y1973 = '1973';
    case Y1974 = '1974';
    case Y1975 = '1975';
    case Y1976 = '1976';
    case Y1977 = '1977';
    case Y1978 = '1978';
    case Y1979 = '1979';

    case Y1980 = '1980';
    case Y1981 = '1981';
    case Y1982 = '1982';
    case Y1983 = '1983';
    case Y1984 = '1984';
    case Y1985 = '1985';
    case Y1986 = '1986';
    case Y1987 = '1987';
    case Y1988 = '1988';
    case Y1989 = '1989';

    case Y1990 = '1990';
    case Y1991 = '1991';
    case Y1992 = '1992';
    case Y1993 = '1993';
    case Y1994 = '1994';
    case Y1995 = '1995';
    case Y1996 = '1996';
    case Y1997 = '1997';
    case Y1998 = '1998';
    case Y1999 = '1999';

    case Y2000 = '2000';
    case Y2001 = '2001';
    case Y2002 = '2002';
    case Y2003 = '2003';
    case Y2004 = '2004';
    case Y2005 = '2005';
    case Y2006 = '2006';
    case Y2007 = '2007';
    case Y2008 = '2008';
    case Y2009 = '2009';

    case Y2010 = '2010';
    case Y2011 = '2011';
    case Y2012 = '2012';
    case Y2013 = '2013';
    case Y2014 = '2014';
    case Y2015 = '2015';
    case Y2016 = '2016';
    case Y2017 = '2017';
    case Y2018 = '2018';
    case Y2019 = '2019';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $this->value;
    }
}