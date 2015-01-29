Name:		nagvis
Version:	1.7.10+dfsg1
Release:	1kaji0.2
Summary:	NagVis

Group:		Nagvis
License:	GPLv2
URL:        https://github.com/kaji-project/nagvis
Source0:    %{name}_%{version}.orig.tar.gz
Requires: php, php-mysql, php-gd, httpd, graphviz-gd, php-mbstring
Requires: graphviz >= 2.14
BuildArch: noarch

%description
NagVis is a visualization addon for the well known network managment system Nagios.
NagVis can be used to visualize Nagios Data, e.g. to display IT 
processes like a mail system or a network infrastructure.
Key features :
  - The WUI has been removed and merged into the regular frontend. You can now lock/unlock specific or all map objects to edit them. The maps can now be edited in place.

  - A sidebar which can be opened clicking on the "Open" button in the header menu.

  - The colors and ranges of weathermap lines can now be customized per object. The middle of line objects with two parts can be repositioned. This makes it possible for lines go round corners.

  - The header menu can now be hidden to safe space on the display. The state of the header menu is stored for each user.

  - Under the hood NagVis 1.6 introduces the object_id attribute which is meant to identify an object on a map. It is automatically added to each object if none is configured.

  - The nagvis main configuration can be split in parts using the conf.d directory.

  - The hosts in the automaps have now a context link "make root" to browse the parent tree.

  - New backend for NagiosBP which makes it possible to show up NagiosBP aggregations as servicegroups in NagVis.


%prep
%setup -q
# Apply all patches
ls .
for patch_file in $(cat debian/patches/series | grep -v "^#")
do
%{__patch} -p1 < debian/patches/$patch_file
done


%build 

%install

%{__rm} -rf %{buildroot}


# /var/lib/nagvis
%{__install} -d -m 0755 %{buildroot}%{_var}/lib/%{name}
mv share/userfiles/ %{buildroot}%{_var}/lib/%{name}

# /var/share/nagvis
%{__install} -d -m 0755 %{buildroot}%{_datadir}/%{name}
%{__install} -d -m 0755 %{buildroot}%{_datadir}/%{name}/tmpl
%{__install} -d -m 0755 %{buildroot}%{_datadir}/%{name}/tmpl/cache
%{__install} -d -m 0755 %{buildroot}%{_datadir}/%{name}/tmpl/compile
cp -r share/ %{buildroot}%{_datadir}/%{name}
cp -r docs/ %{buildroot}%{_datadir}/%{name}


%{__install} -d -m 0755 %{buildroot}%{_sysconfdir}/%{name}
%{__install} -d -m 0755 %{buildroot}%{_sysconfdir}/%{name}/profiles
%{__install} -d -m 0755 %{buildroot}%{_sysconfdir}/%{name}/defaults
mv etc/nagvis.ini.php-sample %{buildroot}%{_sysconfdir}/%{name}/defaults
mv etc/apache2-nagvis.conf-sample %{buildroot}%{_sysconfdir}/%{name}/defaults
cp -r etc/* %{buildroot}%{_sysconfdir}/%{name}


%{__install} -d -m 0755 %{buildroot}%{_sysconfdir}/httpd/conf.d


# Copy kis_icons
%{__install} -d -m 0755 %{buildroot}%{_datadir}/%{name}/userfiles/images/iconsets/
cp -r debian/kis_icons/* %{buildroot}%{_datadir}/%{name}/userfiles/images/iconsets/

# Delete demo files
rm -f %{buildroot}%{_sysconfdir}/%{name}/conf.d/demo.ini.php
rm -f %{buildroot}%{_sysconfdir}/%{name}/maps/demo*.cfg
rm -f %{buildroot}%{_var}/lib/%{name}/userfiles/images/maps/demo*.png \
rm -f %{buildroot}%{_var}/lib/%{name}/userfiles/images/shapes/demo*.png
#rm -rf %{buildroot}%{_datadir}/%{name}/share/userfiles/


%{__install} -d -m 0755 %{buildroot}%{_datadir}/doc/%{name}
%{__install} -d -m 0755 %{buildroot}%{_datadir}/doc/%{name}/share/
%{__install} -d -m 0755 %{buildroot}%{_var}/cache/%{name}
ln -sf /var/lib/nagvis/userfiles %{buildroot}%{_datadir}/%{name}/share/userfiles
ln -sf /usr/share/nagvis/docs %{buildroot}%{_datadir}/doc/%{name}/html
ln -sf /usr/share/nagvis/docs %{buildroot}%{_datadir}/%{name}/share/docs
ln -sf /etc/nagvis %{buildroot}%{_datadir}/%{name}/etc
ln -sf %{_var}/cache/%{name} %{buildroot}%{_datadir}/%{name}/var
ln -sf /usr/share/nagvis/defaults/nagvis.ini.php-sample %{buildroot}%{_datadir}/doc/%{name}/nagvis.ini.php-sample
ln -sf /usr/share/nagvis/defaults/apache2-nagvis.conf-sample %{buildroot}%{_datadir}/doc/%{name}/apache2-nagvis.conf-sample
ln -sf /etc/nagvis/apache2.conf %{buildroot}%{_sysconfdir}/httpd/conf.d/nagvis.conf
ln -sf %{_var}/cache/%{name} %{buildroot}%{_datadir}/%{name}/share/var

%postun

%post
# Configuration database informations
sed -i 's:;base=.*:base="/usr/share/nagvis/":' %{_sysconfdir}/nagvis/nagvis.ini.php
sed -i 's:;htmlbase=.*:htmlbase="/nagvis":' %{_sysconfdir}/nagvis/nagvis.ini.php
sed -i 's/;backend=.*/backend="ndomy_1"/' %{_sysconfdir}/nagvis/nagvis.ini.php
sed -i 's/;dbhost=.*/dbhost="localhost"/' %{_sysconfdir}/nagvis/nagvis.ini.php
sed -i 's/;dbname=.*/dbname="nagios"/' %{_sysconfdir}/nagvis/nagvis.ini.php
sed -i 's/;dbuser=.*/dbuser="root"/' %{_sysconfdir}/nagvis/nagvis.ini.php
sed -i 's/;dbpass.*/dbpass=""/' %{_sysconfdir}/nagvis/nagvis.ini.php
sed -i 's/^;dbinstancename=.*$/dbinstancename="default"/' %{_sysconfdir}/nagvis/nagvis.ini.php

# check upgrade 
if [ "$1" = "2" ] ; then
    sed -i '/^allowedforconfig=/d' %{_sysconfdir}/nagvis/nagvis.ini.php
    sed -i '/^autoupdatefreq=/d' %{_sysconfdir}/nagvis/nagvis.ini.php
    sed -i '/^htmlwuijs=/d' %{_sysconfdir}/nagvis/nagvis.ini.php
    sed -i '/^wuijs=/d' %{_sysconfdir}/nagvis/nagvis.ini.php
    sed -i '/^usegdlibs=/d' %{_sysconfdir}/nagvis/nagvis.ini.php
    sed -i '/^displayheader=/d' %{_sysconfdir}/nagvis/nagvis.ini.php
    sed -i '/^hovertimeout=/d' %{_sysconfdir}/nagvis/nagvis.ini.php
#    sed -i '/^allowed_for_config=/d' %{_sysconfdir}/nagvis/maps/*.cfg
#    sed -i '/^allowed_user=/d' %{_sysconfdir}/nagvis/maps/*.cfg
#    sed -i '/^allowed_for_config=/d' %{_sysconfdir}/nagvis/automaps/*.cfg
fi

%pre
# check upgrade 
if [ "$1" = "2" ] ; then
    if [ -f %{_datadir}/%{name}/share/server/core/defines/global.php ]; then
        OLD_VERSION=$(grep CONST_VERSION %{_datadir}/%{name}/share/server/core/defines/global.php | awk -F"'" '{print $4}')
        if [ "$OLD_VERSION" == "1.5.9" ] || [ "$OLD_VERSION" == "1.5.10" ]; then
            cd %{_var}/lib/%{name}
            for file in $(ls *); do
                rm -fr $file
            done
        fi
    fi
fi

getent group apache >/dev/null || groupadd -r apache
getent passwd apache >/dev/null || \
useradd -r -g apache -d /var/www -s /sbin/nologin \
-c "Apache" apache
exit 0

%clean
rm -rf %{buildroot}

%files
%defattr(-,root,root,0755)
%doc INSTALL LICENCE README
%{_datadir}/doc/%{name}/
%attr(755,apache,apache) %{_sysconfdir}/nagvis/defaults
%{_sysconfdir}/httpd/conf.d/nagvis.conf
%attr(755,apache,apache) %dir %{_var}/cache/%{name}
%attr(775,root,apache) %dir %{_sysconfdir}/nagvis
%attr(755,apache,apache) %dir %{_sysconfdir}/nagvis/maps
#%attr(755,apache,apache) %dir %{_sysconfdir}/nagvis/automaps
%attr(755,apache,apache) %dir %{_sysconfdir}/nagvis/geomap
%attr(755,apache,apache) %dir %{_sysconfdir}/nagvis/conf.d
%attr(755,apache,apache) %dir %{_sysconfdir}/nagvis/profiles
#%attr(640,apache,apache) %config(noreplace) %{_sysconfdir}/nagvis/nagvis.ini.php
#%attr(-,apache,apache) %config(noreplace) %{_sysconfdir}/nagvis/maps/*.cfg
#%attr(-,apache,apache) %config(noreplace) %{_sysconfdir}/nagvis/automaps/*.cfg
%attr(-,apache,apache) %config(noreplace) %{_sysconfdir}/nagvis/geomap/*.xml
%attr(-,apache,apache) %config(noreplace) %{_sysconfdir}/nagvis/geomap/*.csv
#%attr(-,apache,apache) %{_sysconfdir}/nagvis/conf.d/*.ini.php
%attr(-,apache,apache) %{_var}/lib/%{name}


%defattr(-,apache,apache,0775)
%{_datadir}/%{name}

%changelog
* Wed Jan 4 2012 Olivier LI-KIANG-CHEONG <olivier@gezen.fr> 1.6.1-fan.4
- Create directory /etc/nagvis/profiles

* Wed Jan 4 2012 Olivier LI-KIANG-CHEONG <olivier@gezen.fr> 1.6.1-fan.3
- clean /var/lib/nagvis for upgrade 1.5 -> 1.6
- clean file config /etc/nagvis/* for upgrade 1.5 -> 1.6

* Mon Jan 2 2012 Olivier LI-KIANG-CHEONG <olivier@gezen.fr> 1.6.1-fan.2
- Fix backend_id demo for maps example
- Replace admin user by nagiosadmin

* Fri Dec 23 2011 Charles JUDITH <contact@charlesjudith.com> 1.6.1-fan.1
- Upgrade to 1.6.1

* Mon Oct 3 2011 Olivier LI-KIANG-CHEONG <olivier@gezen.fr> 1.5.10-fan.1
- Upgrade to 1.5.10

* Mon Jul 11 2011 Olivier LI-KIANG-CHEONG <olivier@gezen.fr> 1.5.9-fan.1
- Upgrade to 1.5.9

* Tue Mar 1 2011 Olivier LI-KIANG-CHEONG <olivier@gezen.fr> 1.5.8-fan.1
- Upgrade to 1.5.8

* Wed Oct 27 2010 Olivier LI-KIANG-CHEONG <olivier@gezen.fr> 1.5.4-fan.1
- Upgrade to 1.5.4

* Wed Oct 27 2010 Olivier LI-KIANG-CHEONG <olivier@gezen.fr> 1.5.1-fan.1
- Upgrade to 1.5.1
