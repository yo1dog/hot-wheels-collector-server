#include <stdlib.h>
#include <stdio.h>
#include <errno.h>
#include <string.h>

#define PNG_DEBUG 3
#include <png.h>

void clean();

int alpha_tolerance;
int bounding_padding;

FILE *fp_in;
FILE *fp_out;

png_structp png_reader;
png_infop   png_reader_info;

png_structp png_writer;
png_infop   png_writer_info;

int      image_width;
int      image_height;
png_byte image_color_type;
png_byte image_bit_depth;

int num_channels = 4;

int bounding_first_x;
int bounding_first_y;
int bounding_last_x;
int bounding_last_y;

int new_image_width;
int new_image_height;

png_bytep* image_row_pointers;
png_bytep* new_image_row_pointers;




int main(int argc, char** argv)
{
	// make sure we have our args
	if (argc != 5)
	{
		printf("Usage: hwip imagein.png imageout.png alphatolerance(0 - 253) padding\n");
		return 1;
	}
	
	alpha_tolerance = atoi(argv[3]);
	
	if (alpha_tolerance < 0 || alpha_tolerance > 253)
	{
		printf("alpha tolerance must be between 0 - 253\n");
		return 1;
	}
	
	bounding_padding = atoi(argv[4]);
	
	// read the image
	int status = read_image(argv[1]);
	if (status != 0)
	{
		clean();
		return status;
	}
	
	// process the image
	status = process_image();
	if (status != 0)
	{
		clean();
		return status;
	}
	
	// write the image
	status = write_image(argv[2]);
	if (status != 0)
	{
		clean();
		return status;
	}
	
	return 0;
}




int read_image(char* filename)
{
	// open file
	fp_in = fopen(filename, "rb");
	if (!fp_in)
	{
		printf("ERROR: Unable to open \"%s\" for reading: %s\n", filename, strerror(errno));
		return 1;
	}
	
	// make sure this is a png
	char header[8];
	fread(header, 1, 8, fp_in);
	
	if (png_sig_cmp(header, 0, 8))
	{
		printf("ERROR: Input file is not a PNG\n");
		return 1;
	}
	
	
	// create structs
	png_reader = png_create_read_struct(PNG_LIBPNG_VER_STRING, NULL, NULL, NULL);
	
	if (!png_reader)
	{
		printf("ERROR: png_create_read_struct failed\n");
		return 1;
	}
	
	png_reader_info = png_create_info_struct(png_reader);
	if (!png_reader_info)
	{
		printf("ERROR: png_create_info_struct failed\n");
		return 1;
	}
	
	if (setjmp(png_jmpbuf(png_reader)))
	{
		printf("ERROR: setjmp failed\n");
		return 1;
	}
	
	
	// use our file pointer
	png_init_io(png_reader, fp_in);
	
	// we already read the first 8 bytes
	png_set_sig_bytes(png_reader, 8);
	
	// read the png info
	png_read_info(png_reader, png_reader_info);
	
	image_width      = png_get_image_width (png_reader, png_reader_info);
	image_height     = png_get_image_height(png_reader, png_reader_info);
	image_color_type = png_get_color_type  (png_reader, png_reader_info);
	image_bit_depth  = png_get_bit_depth   (png_reader, png_reader_info);
	
	// handle interlacing
	png_set_interlace_handling(png_reader);
	
	// update the png info (necessary? no transformations)
	png_read_update_info(png_reader, png_reader_info);
	
	
	// alloc our row pointers
	image_row_pointers = (png_bytep*)malloc(sizeof(png_bytep) * image_height);
	
	int y;
	for (y = 0; y < image_height; ++y)
		image_row_pointers[y] = (png_byte*)malloc(png_get_rowbytes(png_reader, png_reader_info));
	
	// finally... read our image data!
	png_read_image(png_reader, image_row_pointers);
	
	// done reading
	png_destroy_read_struct(&png_reader, &png_reader_info, NULL);
	png_reader_info = NULL;
	png_reader = NULL;
	
	// close the file
	fclose(fp_in);
	fp_in = NULL;
	
	return 0;
}





int process_image(void)
{
	// make sure the format is RGBA
	if (image_color_type != PNG_COLOR_TYPE_RGBA)
	{
		printf("ERROR: The PNG is not RGBA\n");
		return 1;
	}
	
	// make sure we have the right bit depth
	/*if (image_bit_depth != 8)
	{
		printf("ERROR: The PNG bitdepth is not 8\n");
		
		clean();
		return;
	}*/
	
	bounding_first_x = image_width;
	bounding_first_y = -1;
	bounding_last_x  = -1;
	bounding_last_y  = -1;
	
	int y, x;
	for (y = 0; y < image_height; ++y)
	{
		png_byte* row = image_row_pointers[y];
		
		for (x = 0; x < image_width; ++x)
		{
			png_byte* ptr = &(row[x * num_channels]);
			int alpha = ptr[3]; // alpha is last value
			
			// if we have a non-transparent pixel
			if (alpha > alpha_tolerance)
			{
				// we are going top-left to bottom-right row by row
				// the first row we find a non-transparent pixel in is the first y
				if (bounding_first_y == -1)
					bounding_first_y = y;
				
				// the last row we find a non-transparent pixel in is the last y
				bounding_last_y = y;
				
				// check if the column is less than the first x
				if (x < bounding_first_x)
					bounding_first_x = x;
				
				// check if the column is greter than the last x
				if (x > bounding_last_x)
					bounding_last_x = x;
			}
		}
	}
	
	// if we found one non-transparent pixel, then bounding_first_y will be set along with the rest
	if (bounding_first_y == -1)
	{
		// use the whole image
		bounding_first_x = 0;
		bounding_first_y = 0;
		bounding_last_x = image_width - 1;
		bounding_last_y = image_height - 1;
	}
	else
	{
		// add padding
		bounding_first_x -= bounding_padding;
		if (bounding_first_x < 0)
			bounding_first_x = 0;
	
		bounding_first_y -= bounding_padding;
		if (bounding_first_y < 0)
			bounding_first_y = 0;
	
		bounding_last_x += bounding_padding;
		if (bounding_last_x > image_width - 1)
			bounding_last_x = image_width - 1;
	
		bounding_last_y += bounding_padding;
		if (bounding_last_y > image_height - 1)
			bounding_last_y = image_height - 1;
	}
	
	// printf("Bounding Box: (%i, %i) (%i, %i)\n", bounding_first_x, bounding_first_y, bounding_last_x, bounding_last_y);
	
	new_image_width  = (bounding_last_x - bounding_first_x) + 1;
	new_image_height = (bounding_last_y - bounding_first_y) + 1;
	
	return 0;
}




int write_image(char* filename)
{
	// open file
	fp_out = fopen(filename, "wb");
	if (!fp_out)
	{
		printf("ERROR: Unable to open \"%s\" for writing: %s\n", filename, strerror(errno));
		return 1;
	}
	
	// create structs
	png_writer = png_create_write_struct(PNG_LIBPNG_VER_STRING, NULL, NULL, NULL);
	
	if (!png_writer)
	{
		printf("ERROR: png_create_write_struct failed\n");
		return 1;
	}
	
	png_writer_info = png_create_info_struct(png_writer);
	if (!png_writer_info)
	{
		printf("ERROR: png_create_info_struct failed\n");
		return 1;
	}
	
	if (setjmp(png_jmpbuf(png_writer)))
	{
		printf("ERROR: setjmp failed\n");
		return 1;
	}
	
	// use our file pointer
	png_init_io(png_writer, fp_out);
	
	// write header
	png_set_IHDR(
			png_writer, png_writer_info, new_image_width, new_image_height,
			image_bit_depth, image_color_type, PNG_INTERLACE_NONE,
			PNG_COMPRESSION_TYPE_BASE, PNG_FILTER_TYPE_BASE);
	
	png_write_info(png_writer, png_writer_info);
	
	
	// alloc our row pointers and copy pixels from bounding box
	new_image_row_pointers = (png_bytep*)malloc(sizeof(png_bytep) * new_image_height);
	
	int y;
	for (y = 0; y < new_image_height; ++y)
	{
		new_image_row_pointers[y] = (png_byte*)malloc(png_get_rowbytes(png_writer, png_writer_info));
		
		// copy one row of data
		memcpy(new_image_row_pointers[y], &(image_row_pointers[bounding_first_y + y][bounding_first_x * num_channels]), new_image_width * num_channels);
	}
	
	
	// write bytes
	png_write_image(png_writer, new_image_row_pointers);
	
	// done writing
	png_write_end(png_writer, NULL);

	png_destroy_write_struct(&png_writer, &png_writer_info);
	png_writer_info = NULL;
	png_writer = NULL;
	
	for (y = 0; y < image_height; ++y)
		free(image_row_pointers[y]);
	
	free(image_row_pointers);
	image_row_pointers = NULL;
	
	for (y = 0; y < new_image_height; ++y)
		free(new_image_row_pointers[y]);
	
	free(new_image_row_pointers);
	new_image_row_pointers = NULL;
	
	// close the file
	fclose(fp_out);
	fp_out = NULL;
	
	return 0;
}





void clean()
{
	if (png_reader)
	{
		if (png_reader_info)
			png_destroy_info_struct(png_reader, &png_reader_info);
		
		png_destroy_read_struct(&png_reader, NULL, NULL);
	}
	
	if (png_writer)
	{
		if (png_writer_info)
			png_destroy_info_struct(png_writer, &png_writer_info);
		
		png_destroy_write_struct(&png_writer, NULL);
	}
	
	if (fp_in)
		fclose(fp_in);
	
	if (fp_out)
		fclose(fp_out);
	
	if (image_row_pointers)
	{
		int y;
		for (y = 0; y < image_height; ++y)
			free(image_row_pointers[y]);
		
		free(image_row_pointers);
	}
	
	if (new_image_row_pointers)
	{
		int y;
		for (y = 0; y < new_image_height; ++y)
			free(new_image_row_pointers[y]);
		
		free(new_image_row_pointers);
	}
}
