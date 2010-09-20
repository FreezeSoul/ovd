#include <stdio.h>
#include <stdlib.h>
#include <jni.h>
#include <X11/Xatom.h>
#include <X11/X.h>
#include <X11/Xlib.h>

typedef struct rect_ {
	int x;
	int y;
	int width;
	int height;
} Rectangle;

Rectangle* getWorkAreaRect();


int main() {
	Rectangle* rect = getWorkAreaRect();
	if (rect == NULL)
		return -1;

	printf("x_offset: %d\n", rect->x);
	printf("y_offset: %d\n", rect->y);
	printf("width: %d\n", rect->width);
	printf("height: %d\n", rect->height);

	free(rect);
	
	return 0;
}

Rectangle* getWorkAreaRect() {
	Display* dpy = XOpenDisplay("");
	Window rootWnd;

	Atom workarea_atom;
	int status;
	Atom actual_type;
	int actual_format;
	unsigned long nitems;
	unsigned long remaining_bytes;
	unsigned char* data;
	Rectangle* rect;

	if (dpy == NULL) {
		fprintf(stderr, "Cannot open the current display");
		return NULL;
	}
	rootWnd = XRootWindow(dpy, 0);

	workarea_atom = XInternAtom(dpy, "_NET_WORKAREA", True);
	if (workarea_atom == None) {
		fprintf(stderr, "The _NET_WORKAREA atom does not exists");
		return NULL;
	}

	status = XGetWindowProperty(dpy, rootWnd, workarea_atom, 0, ~0L, False, XA_CARDINAL, &actual_type, &actual_format, &nitems, &remaining_bytes, &data);
	if (status != Success) {
		fprintf(stderr, "Getting _NET_WORKAREA atom content failed");
		return NULL;
	}
	if (actual_type != XA_CARDINAL || actual_format != 32 || nitems < 4) {
		fprintf(stderr, "Getting _NET_WORKAREA atom content return bad content");
		return NULL;
	}

	rect = malloc(sizeof(Rectangle));
	if (rect == NULL) {
		fprintf(stderr, "Not enough memory: need %lu bytes", sizeof(Rectangle));
		return NULL;
	}

	rect->x = (int) (data[0] | (data[1] << 8) | (data[2] << 16) | (data[3] << 24));
	rect->y = (int) (data[4] | (data[5] << 8) | (data[6] << 16) | (data[7] << 24));
	rect->width = (int) (data[8] | (data[9] << 8) | (data[10] << 16) | (data[11] << 24));
	rect->height = (int) (data[12] | (data[13] << 8) | (data[14] << 16) | (data[15] << 24));

	XCloseDisplay(dpy);

	return rect;
}

JNIEXPORT jintArray JNICALL Java_org_ulteo_ovd_client_env_WorkArea_getWorkAreaSizeForX(JNIEnv *env, jclass class) {
	Rectangle* rect;
	jintArray area;
	int buff[4];

	rect = getWorkAreaRect();
	if (rect == NULL) {
		area = (*env)->NewIntArray(env,1);
		buff[0] = 0;
		(*env)->SetIntArrayRegion(env, area, 0, 1, (jint*)buff);
	}
	else {
		area = (*env)->NewIntArray(env,4);
		buff[0] = rect->x;
		buff[1] = rect->y;
		buff[2] = rect->width;
		buff[3] = rect->height;
		(*env)->SetIntArrayRegion(env, area, 0, 4, (jint*)buff);

		free(rect);
	}

	return area;
}
